<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UploadService;
use GuzzleHttp\Client;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class ReportedIssuesController extends Controller
{
    private string $issuesTable = 'virtualdesigns_reportedissues_issues';

    public function index(Request $request)
    {
        if ($request->headers->has('Bookingid')) {
            $issues = DB::table($this->issuesTable)
                ->where('booking_id', '=', $request->headers->get('Bookingid'))
                ->get();

            return $this->corsJson($issues, 200);
        }

        $bookingRef = $this->headerValue($request, 'bookingref');
        $allocatedTo = $this->headerValue($request, 'allocatedto');
        $reportedBy = $this->headerValue($request, 'reportedby');
        $propId = $this->headerValue($request, 'propid');
        $limited = $request->headers->has('limited');
        $createdDate = $this->headerValue($request, 'date');
        $stage = $this->headerValue($request, 'stage');
        $isMobile = $request->headers->has('ismobile');
        $page = (int) ($this->headerValue($request, 'page') ?: 1);
        $perPage = (int) ($this->headerValue($request, 'perpage') ?: 20);

        $issues = DB::table($this->issuesTable . ' as issue');

        if ($bookingRef) {
            $bookingIds = DB::table('virtualdesigns_erpbookings_erpbookings')
                ->where('booking_ref', '=', $bookingRef)
                ->pluck('id');
            $issues->whereIn('issue.booking_id', $bookingIds);
        }

        if ($propId) {
            $issues->where('issue.property_id', '=', $propId);
        }

        if ($stage) {
            $stageId = $this->mapStageToId($stage);
            if ($stageId !== null) {
                $issues->where('issue.stage', '=', $stageId);
            }
        }

        if ($allocatedTo) {
            $issues->where('issue.allocated_to_user_id', '=', $allocatedTo);
        }

        if ($reportedBy) {
            $issues->where('issue.reported_by', '=', $reportedBy);
        }

        if ($createdDate) {
            $dates = explode(',', $createdDate);
            if (count($dates) === 2) {
                $start = date('Y-m-d H:i:s', strtotime($dates[0] . ' 00:00:00'));
                $end = date('Y-m-d H:i:s', strtotime($dates[1] . ' 23:59:59'));
                $issues->whereBetween('issue.created_at', [$start, $end]);
            }
        }

        $issues = $this->applyIssueJoins($issues);

        if ($limited) {
            $issues->select('issue.id', 'issue.description', 'issue.stage', 'issue.priority');
        }

        $total = $issues->count();

        if ($isMobile) {
            $issues->skip(($page - 1) * $perPage)->take($perPage);
        }

        $issues = $issues->get();

        foreach ($issues as $issue) {
            $issue->reported_by_user_name = trim(($issue->reported_by_first_name ?? '') . ' ' . ($issue->reported_by_surname ?? ''));
            $issue->next_booking_date = $this->getNextBookingDate($issue->related_property_id ?? null);
            $issue->booking_ref = $issue->booking_ref ?? null;
        }

        if ($isMobile && $issues->isNotEmpty()) {
            $issues[0]->total = $total;
            $issues[0]->currentpage = $page;
            $issues[0]->totalpages = (int) ceil($total / $perPage);
        }

        return response(json_encode($issues, JSON_INVALID_UTF8_SUBSTITUTE), 200)
            ->header('Content-Type', 'application.json')
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function show(int $id)
    {
        $issue = $this->getIssueDetails($id);
        if (!$issue) {
            return $this->corsJson(['error' => 'Issue not found'], 404);
        }

        $allocatedUsers = DB::table('virtualdesigns_reportedissues_allocated_users')->get();

        return $this->corsJson([
            'issue' => $issue,
            'allocatedUsers' => $allocatedUsers,
        ], 200);
    }

    public function store(Request $request)
    {
        $payload = $request->all();
        $userId = $this->headerValue($request, 'userid');

        if (!$userId) {
            return $this->corsJson(['error' => 'Missing user id'], 400);
        }

        $payload['reported_by'] = $userId;

        $propertyId = $payload['property_id'] ?? null;

        if (!empty($payload['task_id'])) {
            $task = DB::table('virtualdesigns_cleans_cleans')->where('id', '=', $payload['task_id'])->first();
            if ($task) {
                $propertyId = $task->property_id;
                $payload['booking_id'] = $task->booking_id;
                $payload['property_id'] = $propertyId;
            }
        } elseif (!empty($payload['booking_id'])) {
            $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $payload['booking_id'])->first();
            if ($booking) {
                $propertyId = $booking->property_id;
                $payload['property_id'] = $propertyId;
            }
        }

        $payload = $this->filteredPayload($payload, $this->issuesTable);
        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        $issueId = DB::table($this->issuesTable)->insertGetId($payload);

        if ($propertyId) {
            DB::table('virtualdesigns_reportedissues_property_issue')->insert([
                'issue_id' => $issueId,
                'property_id' => $propertyId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->storeIssueFiles($issueId, $request->file('issue_files_add', []));

        $this->pushNotify(1144, $issueId, 'create');

        return $this->corsJson($this->getIssueDetails($issueId), 200);
    }

    public function update(Request $request, int $id)
    {
        $payload = $this->filteredPayload($request->all(), $this->issuesTable);
        unset($payload["upload_issue_image_id-{$id}"]);

        if (!empty($payload)) {
            $payload['updated_at'] = now();
            DB::table($this->issuesTable)->where('id', '=', $id)->update($payload);
        }

        $this->storeIssueFiles($id, $request->file('issue_files_add', []));

        return response($this->getIssueDetails($id), 200)
            ->header('Content-Type', 'application.json')
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function destroy(int $id)
    {
        DB::table($this->issuesTable)->where('id', '=', $id)->update(['deleted_at' => now()]);
        DB::table('virtualdesigns_reportedissues_property_issue')
            ->where('issue_id', '=', $id)
            ->update(['deleted_at' => now()]);

        return $this->corsJson(['success' => true], 200);
    }

    public function duplicateIssue(int $id)
    {
        $parentIssue = DB::table($this->issuesTable)->where('id', '=', $id)->first();
        if (!$parentIssue) {
            return $this->corsJson(['error' => 'No record found'], 404);
        }

        $payload = (array) $parentIssue;
        unset($payload['id'], $payload['allocated_to'], $payload['deleted_at'], $payload['updated_at']);

        $payload['is_duplicated'] = 1;
        $payload['parent_issue_id'] = $id;
        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        $duplicateId = DB::table($this->issuesTable)->insertGetId($payload);

        $propertyId = $payload['property_id'] ?? null;
        if (!empty($payload['task_id'])) {
            $task = DB::table('virtualdesigns_cleans_cleans')->where('id', '=', $payload['task_id'])->first();
            $propertyId = $task->property_id ?? $propertyId;
        } elseif (!empty($payload['booking_id'])) {
            $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $payload['booking_id'])->first();
            $propertyId = $booking->property_id ?? $propertyId;
        }

        if ($propertyId) {
            DB::table('virtualdesigns_reportedissues_property_issue')->insert([
                'issue_id' => $duplicateId,
                'property_id' => $propertyId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $this->corsJson($this->getIssueDetails($duplicateId), 200);
    }

    public function allocateUser(Request $request, int $id, ?int $userId = null)
    {
        $issue = DB::table($this->issuesTable)->where('id', '=', $id)->first();
        if (!$issue) {
            return $this->corsJson(['error' => 'Issue not found'], 404);
        }

        if (!empty($issue->allocated_to_user_id)) {
            return $this->corsJson(['message' => 'Please deallocate the current user before allocating anotherone'], 423);
        }

        if ($userId === null) {
            $userId = DB::table('virtualdesigns_reportedissues_allocated_users')->insertGetId([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $alreadyAllocated = DB::table($this->issuesTable)
                ->where('id', '=', $id)
                ->where('allocated_to_user_id', '=', $userId)
                ->exists();
            if ($alreadyAllocated) {
                return $this->corsJson(['message' => 'This user is already allocated to this issue'], 423);
            }
        }

        DB::table($this->issuesTable)->where('id', '=', $id)->update(['allocated_to_user_id' => $userId]);
        $this->pushNotify($userId, $id, 'allocate');

        return $this->corsJson([
            'issue' => $this->getIssueDetails($id),
            'allocatedUsers' => DB::table('virtualdesigns_reportedissues_allocated_users')->get(),
        ], 200);
    }

    public function updateAllocatedUser(Request $request, int $id, int $userId)
    {
        $payload = $request->all();

        DB::table('virtualdesigns_reportedissues_allocated_users')
            ->where('id', '=', $userId)
            ->update(array_merge($payload, ['updated_at' => now()]));

        return $this->corsJson([
            'issue' => $this->getIssueDetails($id),
            'allocatedUsers' => DB::table('virtualdesigns_reportedissues_allocated_users')->get(),
        ], 200);
    }

    public function deallocateUser(Request $request, int $id)
    {
        DB::table($this->issuesTable)->where('id', '=', $id)->update(['allocated_to_user_id' => null]);

        return $this->corsJson($this->getIssueDetails($id), 200);
    }

    public function onSendMail(Request $request, int $id)
    {
        $userId = $this->headerValue($request, 'userid');
        $auth = $userId ? DB::table('users')->where('id', '=', $userId)->first() : null;

        $receiver = DB::table($this->issuesTable)
            ->where('id', '=', $id)
            ->value('allocated_to_user_id');

        if (!$receiver) {
            return $this->corsJson(['error' => 'No allocated user found'], 422);
        }

        $request->merge([
            'issue_id' => $id,
            'from_user_id' => $userId,
            'to_allocated_to_user_id' => $receiver,
        ]);

        $mailId = DB::table('virtualdesigns_reportedissues_mails')->insertGetId([
            'issue_id' => $id,
            'from_user_id' => $userId,
            'to_allocated_to_user_id' => $receiver,
            'subject' => $request->input('subject'),
            'message' => $request->input('message'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $receiverUser = DB::table('virtualdesigns_reportedissues_allocated_users')->where('id', '=', $receiver)->first();
        $data = (object) [
            'mail_to' => $receiverUser->email ?? null,
            'subject' => $request->input('subject'),
            'message' => $request->input('message'),
        ];

        Mail::send('mail.reported_issue', compact('data'), function ($message) use ($data, $auth) {
            $message->from('tasks@hostagents.co.za', 'HostAgents')
                ->to($data->mail_to)
                ->subject($data->subject);

            if ($auth) {
                $message->replyTo($auth->email, $auth->name);
            }
        });

        return $this->corsJson($this->getIssueDetails($id), 200);
    }

    public function onSendIssueFormAsMail(Request $request, int $id)
    {
        $userId = $this->headerValue($request, 'userid');
        $auth = $userId ? DB::table('users')->where('id', '=', $userId)->first() : null;

        $issue = $this->getIssueDetails($id);
        if (!$issue || !$issue->allocated_to_user_id) {
            return $this->corsJson(['error' => 'Issue or allocated user not found'], 422);
        }

        $selectedKeys = [
            'created_at',
            'description',
            'display_on_owner_login',
            'internal_notes',
            'internal_reference',
            'maintenance_note',
            'next_booking_date',
            'priority',
            'related_property',
            'reported_by',
            'stage',
        ];

        $formData = [];
        foreach ($selectedKeys as $fieldName) {
            $formData[$fieldName] = $issue->$fieldName ?? null;
        }

        $subject = 'Please find the following task that\'s been allocated to you at ' . $formData['related_property'];
        $message = $this->makeIssueMailInfoTable($formData);

        DB::table('virtualdesigns_reportedissues_mails')->insert([
            'issue_id' => $id,
            'from_user_id' => $auth->id ?? null,
            'to_allocated_to_user_id' => $issue->allocated_to_user_id,
            'subject' => $subject,
            'message' => $message,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $receiver = DB::table('virtualdesigns_reportedissues_allocated_users')->where('id', '=', $issue->allocated_to_user_id)->first();
        $data = (object) [
            'mail_to' => $receiver->email ?? null,
            'subject' => $subject,
            'message' => $message,
            'imgs' => $request->input('img'),
        ];

        Mail::send('mail.reported_issue', compact('data'), function ($message) use ($data, $auth) {
            $message->from('tasks@hostagents.co.za', 'HostAgents');
            $message->to($data->mail_to);

            if ($auth) {
                $message->replyTo($auth->email, $auth->name);
            }

            $message->subject($data->subject);

            if (!empty($data->imgs)) {
                foreach ($data->imgs as $path) {
                    $localPath = $this->resolveStoragePath($path);
                    if ($localPath && file_exists($localPath)) {
                        $message->attach($localPath);
                    }
                }
            }
        });

        return $this->corsJson($this->getIssueDetails($id), 200);
    }

    private function applyIssueJoins($issues)
    {
        return $issues
            ->leftJoin('virtualdesigns_properties_properties as property', 'issue.property_id', '=', 'property.id')
            ->leftJoin('virtualdesigns_reportedissues_allocated_users as allocated_to', 'issue.allocated_to_user_id', '=', 'allocated_to.id')
            ->leftJoin('users as reported_by', 'issue.reported_by', '=', 'reported_by.id')
            ->leftJoin('virtualdesigns_erpbookings_erpbookings as bookings', 'issue.booking_id', '=', 'bookings.id')
            ->select(
                'issue.*',
                'property.name as related_property',
                'property.id as related_property_id',
                'allocated_to.name as allocated_to_user_name',
                'reported_by.name as reported_by_first_name',
                'reported_by.surname as reported_by_surname',
                'bookings.booking_ref as booking_ref'
            );
    }

    private function getIssueDetails(int $id)
    {
        $issue = $this->applyIssueJoins(DB::table($this->issuesTable . ' as issue'))
            ->where('issue.id', '=', $id)
            ->first();

        if (!$issue) {
            return null;
        }

        $issue->reported_by_user_name = trim(($issue->reported_by_first_name ?? '') . ' ' . ($issue->reported_by_surname ?? ''));

        $mails = DB::table('virtualdesigns_reportedissues_mails')->where('issue_id', '=', $issue->id)->get();
        $issueFiles = DB::table('system_files')
            ->where('attachment_id', '=', $issue->id)
            ->where('attachment_type', '=', 'Virtualdesigns\\ReportedIssues\\Models\\ReportedIssues')
            ->get();

        foreach ($issueFiles as $issueFile) {
            $issueFile->path = Storage::disk('public')->url($issueFile->disk_name);
        }

        $issue->mails = $mails;
        $issue->issue_files = $issueFiles;
        $issue->next_booking_date = $this->getNextBookingDate($issue->related_property_id ?? null);

        if (!empty($issue->booking_id)) {
            $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $issue->booking_id)->first();
            $issue->booking_ref = $booking->booking_ref ?? null;
        }

        if (!empty($issue->property_id)) {
            $opInfo = DB::table('virtualdesigns_operationalinformation_operationalinformation')
                ->where('property_id', '=', $issue->property_id)
                ->value('internal_notes');
            $issue->maintenance_notes = $opInfo;
        }

        return $issue;
    }

    private function getNextBookingDate(?int $propertyId): ?string
    {
        if (!$propertyId) {
            return null;
        }

        $nextBooking = DB::table('virtualdesigns_erpbookings_erpbookings')
            ->where('property_id', '=', $propertyId)
            ->where('arrival_date', '>=', date('Y-m-d'))
            ->where('status', '!=', 1)
            ->whereNull('deleted_at')
            ->orderBy('arrival_date', 'asc')
            ->first();

        return $nextBooking->arrival_date ?? null;
    }

    private function storeIssueFiles(int $issueId, $files): void
    {
        if (empty($files)) {
            return;
        }

        $uploadService = new UploadService();
        foreach ($files as $file) {
            $uploadService->storeAttachment(
                $file,
                'Virtualdesigns\\ReportedIssues\\Models\\ReportedIssues',
                $issueId,
                'issue_files'
            );
        }
    }

    private function mapStageToId(string $stage): ?int
    {
        return match ($stage) {
            'In progress' => 1,
            'Allocated' => 2,
            'Completed' => 3,
            'More Info Needed' => 4,
            'Awaiting Quote' => 5,
            'Awaiting Approval' => 6,
            'Booked In' => 7,
            'Work in Progress' => 8,
            'Ignored by Owner' => 9,
            'Rejected by PM' => 10,
            default => null,
        };
    }

    private function makeIssueMailInfoTable(array $data): string
    {
        $keysToIgnore = ['img', 'to_allocated_to_user_id', 'id', 'display_on_owner_login'];
        $table = '<table>';

        foreach ($data as $key => $value) {
            if (!in_array($key, $keysToIgnore, true)) {
                $label = ucwords(str_replace('_', ' ', (string) $key));
                $table .= "<tr><th style='padding: 5px;'>{$label}</th><td style='padding: 5px 15px;'>{$value}</td></tr>";
            }
        }

        $table .= '</table>';

        return $table;
    }

    private function resolveStoragePath(string $path): ?string
    {
        if (str_contains($path, '/storage/')) {
            $relative = ltrim(str_replace('/storage/', '', parse_url($path, PHP_URL_PATH) ?? ''), '/');
            if ($relative && Storage::disk('public')->exists($relative)) {
                return Storage::disk('public')->path($relative);
            }
        }

        return is_file($path) ? $path : null;
    }

    private function headerValue(Request $request, string $key): ?string
    {
        $value = $request->header($key);
        if (is_array($value)) {
            return $value[0] ?? null;
        }

        return $value;
    }

    private function filteredPayload(array $payload, string $table): array
    {
        $columns = DB::getSchemaBuilder()->getColumnListing($table);
        return array_intersect_key($payload, array_flip($columns));
    }

    private function pushNotify(int $userId, int $recordId, string $mode): string
    {
        $tokens = DB::table('react_user_tokens')->where('user_id', '=', $userId)->get();
        if ($tokens->isEmpty()) {
            return 'success';
        }

        $client = new Client();
        [$title, $body] = match ($mode) {
            'create' => ['New Issue Created', 'A new reported issue has been created'],
            'allocate' => ['New Issue Allocated', 'A new reported issue has been allocated to you'],
            default => ['Issue Updated', 'One of the reported issues allocated to you has been updated'],
        };

        foreach ($tokens as $token) {
            try {
                $client->post('https://exp.host/--/api/v2/push/send', [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'to' => $token->user_token,
                        'title' => $title,
                        'body' => $body,
                        'content-available' => 1,
                        'data' => ['id' => $recordId, 'type' => 'issue'],
                    ],
                ]);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return 'success';
    }

    private function corsJson($data, int $status)
    {
        return response()
            ->json($data, $status)
            ->header('Content-Type', 'application.json')
            ->header('Access-Control-Allow-Origin', '*');
    }
}
