<?php

namespace App\Http\Controllers\Api;

use App\Events\ResourceChanged;
use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TasksController extends Controller
{
    private string $cleansTable = 'virtualdesigns_cleans_cleans';

    public function cmp($a, $b): int
    {
        $a = (array) $a;
        $b = (array) $b;

        return strcmp((string) ($b['is_lastminute'] ?? ''), (string) ($a['is_lastminute'] ?? ''));
    }

    public function index(Request $request): JsonResponse
    {
        $this->assertApiKey($request);

        $userId = (int) $request->header('userid');
        $bookingId = $request->header('bookingid');
        $taskDate = null;
        $page = null;
        $perPage = 20;
        $bookingRef = null;
        $supplierId = null;
        $taskType = null;
        $propId = null;
        $jobDone = null;

        if ($bookingId === null && $request->header('ismobile') !== null) {
            if ($request->header('date') !== null) {
                $taskDate = explode(',', (string) $request->header('date'));
            }

            if ($request->header('page') !== null) {
                $page = (int) $request->header('page');
            }

            if ($request->header('perpage') !== null) {
                $perPage = (int) $request->header('perpage');
            }

            if ($request->header('bookingref') !== null) {
                $bookingRef = (string) $request->header('bookingref');
                $bookingId = DB::table('virtualdesigns_erpbookings_erpbookings')
                    ->where('booking_ref', 'like', '%' . $bookingRef . '%')
                    ->pluck('id')
                    ->toArray();
            }

            if ($request->header('supplierid') !== null) {
                $supplierId = $request->header('supplierid');
            }

            if ($request->header('tasktype') !== null) {
                $taskType = $request->header('tasktype');
            }

            if ($request->header('propid') !== null) {
                $propId = $request->header('propid');
            }

            if ($request->header('jobdone') !== null) {
                $jobDone = $request->header('jobdone');
            }
        }

        $groupId = $this->getUserGroupId($userId) ?? 2;

        if ($groupId === 2) {
            $cleans = DB::table($this->cleansTable)
                ->whereNull($this->cleansTable . '.deleted_at');

            if ($bookingId !== null) {
                if (is_array($bookingId)) {
                    $cleans = $cleans
                        ->whereIn($this->cleansTable . '.booking_id', $bookingId)
                        ->where($this->cleansTable . '.status', '=', 0);
                } else {
                    $cleans = $cleans->where($this->cleansTable . '.booking_id', '=', $bookingId);
                }
            } else {
                $cleans = $cleans->where($this->cleansTable . '.status', '=', 0);
            }

            if ($taskDate !== null) {
                $cleans = $cleans
                    ->where($this->cleansTable . '.clean_date', '>=', $taskDate[0])
                    ->where($this->cleansTable . '.clean_date', '<=', $taskDate[1]);
            } elseif ($bookingId === null) {
                $year = (int) date('Y') - 1;
                $start = date($year . '-01-01');
                $cleans = $cleans->where($this->cleansTable . '.clean_date', '>=', $start);
            }

            if ($supplierId !== null) {
                $cleans = $cleans->where($this->cleansTable . '.supplier_id', '=', $supplierId);
            }

            if ($taskType !== null) {
                $cleans = $cleans->where($this->cleansTable . '.clean_type', '=', $taskType);
            }

            if ($propId !== null) {
                $cleans = $cleans->where($this->cleansTable . '.property_id', '=', $propId);
            }

            if ($jobDone !== null) {
                $cleans = $cleans->where($this->cleansTable . '.job_done', '=', $jobDone);
            }

            $cleans = $cleans
                ->leftJoin('virtualdesigns_properties_properties as property', $this->cleansTable . '.property_id', '=', 'property.id')
                ->leftJoin('users as supplier', $this->cleansTable . '.supplier_id', '=', 'supplier.id')
                ->leftJoin('virtualdesigns_erpbookings_erpbookings as booking', $this->cleansTable . '.booking_id', '=', 'booking.id')
                ->leftJoin('virtualdesigns_operationalinformation_operationalinformation as opinfo', $this->cleansTable . '.property_id', '=', 'opinfo.property_id')
                ->where('booking.status', '!=', 1);

            if ($userId === 1636 || $userId === 1709) {
                $cleans = $cleans->where('property.name', 'like', '%Winelands Golf Lodges%');
            }

            $cleans = $this->applyTaskSelect($cleans)
                ->orderBy($this->cleansTable . '.clean_date', 'asc')
                ->distinct($this->cleansTable . '.id');
        } elseif ($groupId === 5) {
            $cleans = DB::table($this->cleansTable)
                ->leftJoin('virtualdesigns_properties_properties as property', $this->cleansTable . '.property_id', '=', 'property.id')
                ->whereNull($this->cleansTable . '.deleted_at')
                ->where($this->cleansTable . '.status', '=', 0)
                ->where('property.bodycorp_id', '=', $userId);

            if ($bookingId !== null) {
                if (is_array($bookingId)) {
                    $cleans = $cleans->whereIn($this->cleansTable . '.booking_id', $bookingId);
                } else {
                    $cleans = $cleans->where($this->cleansTable . '.booking_id', '=', $bookingId);
                }
            }

            if ($taskDate !== null) {
                $cleans = $cleans
                    ->where($this->cleansTable . '.clean_date', '>=', $taskDate[0])
                    ->where($this->cleansTable . '.clean_date', '<=', $taskDate[1]);
            } elseif ($bookingId === null) {
                $year = (int) date('Y') - 1;
                $start = date($year . '-01-01');
                $cleans = $cleans->where($this->cleansTable . '.clean_date', '>=', $start);
            }

            if ($supplierId !== null) {
                $cleans = $cleans->where($this->cleansTable . '.supplier_id', '=', $supplierId);
            }

            if ($taskType !== null) {
                $cleans = $cleans->where($this->cleansTable . '.clean_type', '=', $taskType);
            }

            if ($propId !== null) {
                $cleans = $cleans->where($this->cleansTable . '.property_id', '=', $propId);
            }

            if ($jobDone !== null) {
                $cleans = $cleans->where($this->cleansTable . '.job_done', '=', $jobDone);
            }

            $cleans = $cleans
                ->leftJoin('users as supplier', $this->cleansTable . '.supplier_id', '=', 'supplier.id')
                ->leftJoin('virtualdesigns_erpbookings_erpbookings as booking', $this->cleansTable . '.booking_id', '=', 'booking.id')
                ->leftJoin('virtualdesigns_operationalinformation_operationalinformation as opinfo', $this->cleansTable . '.property_id', '=', 'opinfo.property_id')
                ->where('booking.status', '!=', 1)
                ->select($this->taskSelectFields())
                ->orderBy($this->cleansTable . '.clean_date', 'asc')
                ->distinct($this->cleansTable . '.id');
        } else {
            $cleans = DB::table($this->cleansTable)
                ->leftJoin('virtualdesigns_properties_properties as property', $this->cleansTable . '.property_id', '=', 'property.id')
                ->whereNull($this->cleansTable . '.deleted_at')
                ->where($this->cleansTable . '.status', '=', 0)
                ->where($this->cleansTable . '.supplier_id', '=', $userId);

            if ($bookingId !== null) {
                if (is_array($bookingId)) {
                    $cleans = $cleans->whereIn($this->cleansTable . '.booking_id', $bookingId);
                } else {
                    $cleans = $cleans->where($this->cleansTable . '.booking_id', '=', $bookingId);
                }
            }

            if ($taskDate !== null) {
                $cleans = $cleans
                    ->where($this->cleansTable . '.clean_date', '>=', $taskDate[0])
                    ->where($this->cleansTable . '.clean_date', '<=', $taskDate[1]);
            } elseif ($bookingId === null) {
                $start = date('Y-01-01');
                $cleans = $cleans->where($this->cleansTable . '.clean_date', '>=', $start);
            }

            if ($supplierId !== null) {
                $cleans = $cleans->where($this->cleansTable . '.supplier_id', '=', $supplierId);
            }

            if ($taskType !== null) {
                $cleans = $cleans->where($this->cleansTable . '.clean_type', '=', $taskType);
            }

            if ($propId !== null) {
                $cleans = $cleans->where($this->cleansTable . '.property_id', '=', $propId);
            }

            if ($jobDone !== null) {
                $cleans = $cleans->where($this->cleansTable . '.job_done', '=', $jobDone);
            }

            $cleans = $cleans
                ->leftJoin('users as supplier', $this->cleansTable . '.supplier_id', '=', 'supplier.id')
                ->leftJoin('virtualdesigns_erpbookings_erpbookings as booking', $this->cleansTable . '.booking_id', '=', 'booking.id')
                ->leftJoin('virtualdesigns_operationalinformation_operationalinformation as opinfo', $this->cleansTable . '.property_id', '=', 'opinfo.property_id')
                ->where('booking.status', '!=', 1)
                ->select($this->taskSelectFields())
                ->orderBy($this->cleansTable . '.clean_date', 'asc')
                ->distinct($this->cleansTable . '.id');
        }

        $total = (clone $cleans)
            ->distinct($this->cleansTable . '.id')
            ->count($this->cleansTable . '.id');

        if ($page !== null) {
            if ($page === 1) {
                $cleans = $cleans->take($perPage);
            } else {
                $skip = $page === 2 ? 20 : 20 * ($page - 1);
                $cleans = $cleans->skip($skip)->take($perPage);
            }
        }

        $cleans = $cleans->get();
        $finalCleans = [];
        $todayDate = strtotime('today midnight');

        foreach ($cleans as $task) {
            $task->currency = ((int) $task->country_id === 846) ? 'Rs' : 'R';

            $includeTask = $task->quote_confirmed == 1 || ($bookingId !== null && !is_array($bookingId));
            if (!$includeTask) {
                continue;
            }

            if ($bookingId !== null) {
                $diff = $todayDate - strtotime($task->task_date);
                $hours = $diff / (60 * 60);
                $task->is_locked = $hours >= 48;
            }

            $task->is_lastminute = ($task->booking_hours !== null && (int) $task->booking_hours <= 24);
            $finalCleans[] = (array) $task;
        }

        usort($finalCleans, [$this, 'cmp']);

        if (!empty($finalCleans)) {
            $finalCleans[0]['total'] = $total;
            $finalCleans[0]['currentpage'] = $page ?? 1;
            $finalCleans[0]['totalpages'] = (int) ceil($total / 20);
        }

        return $this->corsJson($finalCleans, 200);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $this->assertApiKey($request);

        $cleanRec = DB::table($this->cleansTable)->where('id', '=', $id)->first();
        if ($cleanRec === null) {
            return $this->corsJson(['code' => 404, 'message' => 'Task not found'], 404);
        }

        $clean = DB::table($this->cleansTable)
            ->leftJoin('virtualdesigns_properties_properties as property', $this->cleansTable . '.property_id', '=', 'property.id')
            ->leftJoin('users as supplier', $this->cleansTable . '.supplier_id', '=', 'supplier.id')
            ->leftJoin('virtualdesigns_erpbookings_erpbookings as booking', $this->cleansTable . '.booking_id', '=', 'booking.id')
            ->leftJoin('virtualdesigns_operationalinformation_operationalinformation as opinfo', $this->cleansTable . '.property_id', '=', 'opinfo.property_id')
            ->leftJoin('virtualdesigns_erpbookings_guestinfo as guest_info', $this->cleansTable . '.booking_id', '=', 'guest_info.booking_id')
            ->where($this->cleansTable . '.id', '=', $id)
            ->select(
                'guest_info.guest_no as no_guests',
                'opinfo.checkin_info',
                $this->cleansTable . '.electricity_units',
                $this->cleansTable . '.electricity_new_units',
                $this->cleansTable . '.inventory_checked',
                $this->cleansTable . '.bed_status',
                'property.directions_link',
                'property.country_id',
                $this->cleansTable . '.damage_status',
                'booking.client_name',
                'guest_info.guest_contact as client_mobile',
                'guest_info.guest_id_no',
                'opinfo.departure_info',
                'booking.arrival_date',
                'booking.departure_date',
                'booking.arrival_time',
                'booking.departure_time',
                'opinfo.prop_manager_todo_before_checkin',
                'opinfo.prop_manager_todo_after_checkout',
                $this->cleansTable . '.changed_linen',
                'opinfo.welcome_pack_notes',
                'guest_info.guest_id as guest_id_doc',
                $this->cleansTable . '.job_done',
                $this->cleansTable . '.clean_date as task_date',
                'guest_info.eta',
                $this->cleansTable . '.booking_id as booking_id',
                $this->cleansTable . '.status',
                $this->cleansTable . '.id as task_id',
                'booking.booking_ref',
                'property.name as prop_name',
                $this->cleansTable . '.clean_type as task_type'
            )
            ->get()
            ->unique();

        if ($clean->isEmpty()) {
            return $this->corsJson(['code' => 404, 'message' => 'Task not found'], 404);
        }

        $clean = $clean->values();
        $guestDetails = DB::table('virtualdesigns_erpbookings_guestinfo')
            ->where('booking_id', '=', $clean[0]->booking_id)
            ->first();

        if ($guestDetails === null) {
            $booking = DB::table('virtualdesigns_erpbookings_erpbookings')
                ->where('id', '=', $clean[0]->booking_id)
                ->first();

            if ($booking) {
                $clean[0]->no_guests = $booking->no_guests;
                if ($booking->client_mobile !== null && $booking->client_mobile !== 'False' && $booking->client_mobile !== false) {
                    $clean[0]->client_mobile = $booking->client_mobile;
                } else {
                    $clean[0]->client_mobile = $booking->client_phone;
                }
                $clean[0]->guest_id_no = $booking->guest_id_no;
            }
        }

        $inventoryUrl = DB::table('virtualdesigns_property_inventories')
            ->where('property_id', '=', $cleanRec->property_id)
            ->whereNotNull('inventory_url')
            ->orderBy('updated_at', 'desc')
            ->value('inventory_url');

        if ($inventoryUrl) {
            $clean[0]->inv_url = $inventoryUrl;
        } else {
            $inventory = DB::table('virtualdesigns_property_inventories')
                ->where('property_id', '=', $cleanRec->property_id)
                ->orderBy('id', 'desc')
                ->first();
            $clean[0]->inv_url = $inventory->inv_url ?? null;
        }

        $clean[0]->guest_id_file = null;
        if (!empty($clean[0]->guest_id_doc)) {
            $guestFile = DB::table('system_files')->where('id', '=', $clean[0]->guest_id_doc)->first();
            if ($guestFile) {
                $clean[0]->guest_id_file = $guestFile->path;
            }
        }

        if ($cleanRec->clean_type === 'Welcome Pack') {
            $cleanProp = DB::table('virtualdesigns_properties_properties')->where('id', '=', $cleanRec->property_id)->first();
            if ($cleanProp && (int) $cleanProp->ha_packed === 1) {
                $pack = DB::table('virtualdesigns_welcomepacks_welcomepacks')
                    ->where('property_id', '=', $cleanRec->property_id)
                    ->whereNull('deleted_at')
                    ->first();
                if ($pack) {
                    $clean[0]->pack_rec = $pack;
                    $clean[0]->milk_pods = $pack->milk_pods;
                    $clean[0]->coffee_sachet = $pack->coffee_sachet;
                    $clean[0]->tea_five_roses = $pack->tea_five_roses;
                    $clean[0]->suger_sachet_brown = $pack->suger_sachet_brown;
                    $clean[0]->toilet_paper = $pack->toilet_paper;
                    $clean[0]->sunlight_liquid = $pack->sunlight_liquid;
                    $clean[0]->washing_up_sponge = $pack->washing_up_sponge;
                    $clean[0]->microfiber_cloth = $pack->microfiber_cloth;
                    $clean[0]->black_bags = $pack->black_bags;
                    $clean[0]->ariel_laundry_capsules = $pack->ariel_laundry_capsules;
                    $clean[0]->finish_dishwasher_tablets = $pack->finish_dishwasher_tablets;
                    $clean[0]->conditioning_shampoo = $pack->conditioning_shampoo;
                    $clean[0]->shower_gel = $pack->shower_gel;
                    $clean[0]->hand_soap = $pack->hand_soap;
                    $clean[0]->nespresso_pods = $pack->nespresso_pods;
                    $clean[0]->rooibos_tea = $pack->rooibos_tea;
                }
            }
        }

        $booking = DB::table('virtualdesigns_erpbookings_erpbookings')
            ->where('id', '=', $cleanRec->booking_id)
            ->first();

        if ($booking) {
            $clean[0]->payment_link = 'https://www.hostagents.co.za/payment/' . $booking->client_email . '/' . $booking->booking_ref . '/' . number_format((float) $booking->booking_amount, 2, '.', '');
        }

        $msaDates = DB::table($this->cleansTable)
            ->where('booking_id', '=', $cleanRec->booking_id)
            ->where('clean_type', '=', 'MSA')
            ->pluck('clean_date');

        $clean[0]->msa_dates = $msaDates;
        $clean[0]->currency = ((int) $clean[0]->country_id === 846) ? 'Rs' : 'R';

        return $this->corsJson($clean, 200);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertApiKey($request);

        return $this->corsJson([], 200);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->assertApiKey($request);

        $cleanRec = DB::table($this->cleansTable)->where('id', '=', $id)->first();
        if ($cleanRec === null) {
            return $this->corsJson(['code' => 404, 'message' => 'Task not found'], 404);
        }

        $payload = $request->all();
        $changeUser = $payload['change_user'] ?? null;

        $updates = [];
        $fields = [
            'electricity_units',
            'electricity_new_units',
            'inventory_checked',
            'bed_status',
            'damage_status',
            'job_done',
            'changed_linen',
            'pack_status',
        ];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $newValue = $payload[$field];
            $oldValue = $cleanRec->$field ?? null;

            if ($oldValue != $newValue) {
                $this->logChange($changeUser, $cleanRec->id, $field, $oldValue, $newValue);
            }

            $updates[$field] = $newValue;
        }

        if (!empty($updates)) {
            DB::table($this->cleansTable)->where('id', '=', $id)->update($updates);
        }

        $clean = DB::table($this->cleansTable)
            ->leftJoin('virtualdesigns_properties_properties as property', $this->cleansTable . '.property_id', '=', 'property.id')
            ->leftJoin('users as supplier', $this->cleansTable . '.supplier_id', '=', 'supplier.id')
            ->leftJoin('virtualdesigns_erpbookings_erpbookings as booking', $this->cleansTable . '.booking_id', '=', 'booking.id')
            ->leftJoin('virtualdesigns_operationalinformation_operationalinformation as opinfo', $this->cleansTable . '.property_id', '=', 'opinfo.property_id')
            ->where($this->cleansTable . '.id', '=', $id)
            ->select(
                'booking.no_guests',
                'opinfo.checkin_info',
                $this->cleansTable . '.electricity_units',
                $this->cleansTable . '.electricity_new_units',
                $this->cleansTable . '.inventory_checked',
                $this->cleansTable . '.bed_status',
                'property.directions_link',
                'opinfo.welcome_pack_notes',
                $this->cleansTable . '.damage_status',
                'booking.client_name',
                'booking.client_mobile',
                'booking.guest_id_no',
                'opinfo.departure_info',
                'booking.arrival_date',
                'booking.departure_date',
                'booking.arrival_time',
                'booking.departure_time',
                'opinfo.prop_manager_todo_before_checkin',
                'opinfo.prop_manager_todo_after_checkout',
                $this->cleansTable . '.changed_linen',
                'opinfo.prop_manager_todo_before_checkin',
                $this->cleansTable . '.id as task_id'
            )
            ->get()
            ->unique();

        if ($clean->isNotEmpty()) {
            $inventory = DB::table('virtualdesigns_property_inventories')
                ->where('property_id', '=', $cleanRec->property_id)
                ->orderBy('id', 'desc')
                ->first();

            $clean[0]->inv_url = $inventory->inv_url ?? null;

            $guestInfo = DB::table('virtualdesigns_erpbookings_guestinfo')
                ->where('booking_id', '=', $cleanRec->booking_id)
                ->first();
            $clean[0]->guest_id_file = null;
            if ($guestInfo && !empty($guestInfo->guest_id)) {
                $guestFile = DB::table('system_files')->where('id', '=', $guestInfo->guest_id)->first();
                if ($guestFile) {
                    $clean[0]->guest_id_file = $guestFile->path;
                }
            }

            $this->pushNotify($cleanRec->supplier_id, $id);
        }

        event(new ResourceChanged('tasks', 'updated', $id, [
            'booking_id' => $cleanRec->booking_id,
            'property_id' => $cleanRec->property_id,
        ]));

        return $this->corsJson($clean, 200);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->assertApiKey($request);

        return $this->corsJson([], 200);
    }

    public function dashboard(Request $request, int $userid): JsonResponse
    {
        $this->assertApiKey($request);

        $propId = $request->header('propid');
        $todayDate = date('Y-m-d');

        $user = DB::table('users')->where('id', '=', $userid)->first();
        $groupId = $this->getUserGroupId($userid);
        if ($user === null) {
            return $this->corsJson(['code' => 404, 'message' => 'User not found'], 404);
        }

        if ($groupId === 2) {
            $tasks = DB::table($this->cleansTable)
                ->whereNull($this->cleansTable . '.deleted_at')
                ->where($this->cleansTable . '.status', '=', 0)
                ->where('clean_date', '=', $todayDate)
                ->where('booking.status', '=', 0)
                ->whereNull('booking.deleted_at')
                ->where('booking.quote_confirmed', '=', 1)
                ->leftJoin('virtualdesigns_erpbookings_erpbookings as booking', $this->cleansTable . '.booking_id', '=', 'booking.id');
        } else {
            $tasks = DB::table($this->cleansTable)
                ->whereNull($this->cleansTable . '.deleted_at')
                ->where($this->cleansTable . '.status', '=', 0)
                ->where('supplier_id', '=', $user->id)
                ->where('clean_date', '=', $todayDate)
                ->where('booking.status', '=', 0)
                ->whereNull('booking.deleted_at')
                ->where('booking.quote_confirmed', '=', 1)
                ->leftJoin('virtualdesigns_erpbookings_erpbookings as booking', $this->cleansTable . '.booking_id', '=', 'booking.id');
        }

        if ($propId !== null) {
            $tasks = $tasks->where($this->cleansTable . '.property_id', '=', $propId);
        }

        $respArray = [];

        $arrivalCleanPending = (clone $tasks)->where('job_done', '=', 0)->where('clean_type', '=', 'Arrival Clean')->count();
        $respArray['arrival_clean_pending'] = $arrivalCleanPending;

        $arrivalCleanCompleted = (clone $tasks)->where('job_done', '=', 1)->where('clean_type', '=', 'Arrival Clean')->count();
        $respArray['arrival_clean_completed'] = $arrivalCleanCompleted;
        $respArray['arrival_clean_total'] = $arrivalCleanPending + $arrivalCleanCompleted;

        $arrivalConciergePending = (clone $tasks)->where('job_done', '=', 0)->where('clean_type', '=', 'Concierge Arrival')->count();
        $respArray['arrival_concierge_pending'] = $arrivalConciergePending;

        $arrivalConciergeCompleted = (clone $tasks)->where('job_done', '=', 1)->where('clean_type', '=', 'Concierge Arrival')->count();
        $respArray['arrival_concierge_completed'] = $arrivalConciergeCompleted;
        $respArray['arrival_concierge_total'] = $arrivalConciergePending + $arrivalConciergeCompleted;

        $welcomePackPending = (clone $tasks)->where('job_done', '=', 0)->where('clean_type', '=', 'Welcome Pack')->count();
        $respArray['welcome_pack_pending'] = $welcomePackPending;

        $welcomePackCompleted = (clone $tasks)->where('job_done', '=', 1)->where('clean_type', '=', 'Welcome Pack')->count();
        $respArray['welcome_pack_completed'] = $welcomePackCompleted;
        $respArray['welcome_pack_total'] = $welcomePackPending + $welcomePackCompleted;

        $msaPending = (clone $tasks)->where('job_done', '=', 0)->where('clean_type', '=', 'MSA')->count();
        $respArray['msa_pending'] = $msaPending;

        $msaCompleted = (clone $tasks)->where('job_done', '=', 1)->where('clean_type', '=', 'MSA')->count();
        $respArray['msa_completed'] = $msaCompleted;
        $respArray['msa_total'] = $msaPending + $msaCompleted;

        $departureCleanPending = (clone $tasks)->where('job_done', '=', 0)->where('clean_type', '=', 'Departure Clean')->count();
        $respArray['departure_clean_pending'] = $departureCleanPending;

        $departureCleanCompleted = (clone $tasks)->where('job_done', '=', 1)->where('clean_type', '=', 'Departure Clean')->count();
        $respArray['departure_clean_completed'] = $departureCleanCompleted;
        $respArray['departure_clean_total'] = $departureCleanPending + $departureCleanCompleted;

        $departureConciergePending = (clone $tasks)->where('job_done', '=', 0)->where('clean_type', '=', 'Concierge Departure')->count();
        $respArray['departure_concierge_pending'] = $departureConciergePending;

        $departureConciergeCompleted = (clone $tasks)->where('job_done', '=', 1)->where('clean_type', '=', 'Concierge Departure')->count();
        $respArray['departure_concierge_completed'] = $departureConciergeCompleted;
        $respArray['departure_concierge_total'] = $departureConciergePending + $departureConciergeCompleted;

        $tasksTotal = $tasks->count();
        $respArray['tasks_total'] = $tasksTotal;

        $tasksPending = (clone $tasks)->where('job_done', '=', 0)->count();
        $respArray['tasks_pending'] = $tasksPending;

        $tasksCompleted = (clone $tasks)->where('job_done', '=', 1)->count();
        $respArray['tasks_completed'] = $tasksCompleted;

        return $this->corsJson($respArray, 200);
    }

    public function setDepartureArrivals(Request $request): JsonResponse
    {
        $this->assertApiKey($request);

        $startDate = (string) $request->header('startdate');
        $endDate = (string) $request->header('enddate');

        $date = $startDate;
        $props = DB::table('virtualdesigns_properties_properties')
            ->whereNull('deleted_at')
            ->where('is_live', '=', 1)
            ->get();

        while ($date <= $endDate) {
            foreach ($props as $prop) {
                $tasks = DB::table($this->cleansTable)
                    ->whereNull('deleted_at')
                    ->where('status', '=', 0)
                    ->where('clean_date', '=', $date)
                    ->where('property_id', '=', $prop->id)
                    ->get();

                $hasArrival = null;
                $hasDeparture = null;

                foreach ($tasks as $task) {
                    if ($task->clean_type === 'Arrival Clean') {
                        $hasArrival = $task->id;
                    }

                    if ($task->clean_type === 'Departure Clean') {
                        $hasDeparture = $task->id;
                    }
                }

                if ($hasArrival !== null && $hasDeparture !== null) {
                    DB::table($this->cleansTable)->where('id', '=', $hasArrival)->update([
                        'price' => 0,
                    ]);
                } elseif ($hasArrival !== null) {
                    $arrivalRec = DB::table($this->cleansTable)->where('id', '=', $hasArrival)->first();
                    if ($arrivalRec && (float) $arrivalRec->price === 0.0) {
                        $managerFees = DB::table('virtualdesigns_propertymanagerfees_propertymanagerfees')
                            ->where('property_id', '=', $prop->id)
                            ->first();

                        if ($managerFees !== null) {
                            $arrivalCleanPrice = (float) $managerFees->arrival_clean;
                            if ($this->isHoliday($date)) {
                                $arrivalCleanPrice *= 1.5;
                            }
                        } else {
                            $arrivalCleanPrice = 0.00;
                        }

                        DB::table($this->cleansTable)->where('id', '=', $hasArrival)->update([
                            'price' => $arrivalCleanPrice,
                        ]);
                    }
                }
            }

            $date = date('Y-m-d', strtotime($date . ' + 1 days'));
        }

        event(new ResourceChanged('tasks', 'updated', 0, [
            'scope' => 'departure-arrivals',
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]));

        return $this->corsJson([
            'code' => 200,
            'message' => 'Back to Backs proccessed succesfully',
        ], 200);
    }

    public function getDamageClaims(Request $request): JsonResponse
    {
        $this->assertApiKey($request);

        $isCompleted = $request->header('completed');

        $damageClaims = DB::table($this->cleansTable)
            ->leftJoin('virtualdesigns_properties_properties as property', $this->cleansTable . '.property_id', '=', 'property.id')
            ->leftJoin('users as supplier', $this->cleansTable . '.supplier_id', '=', 'supplier.id')
            ->leftJoin('virtualdesigns_erpbookings_erpbookings as booking', $this->cleansTable . '.booking_id', '=', 'booking.id')
            ->leftJoin('virtualdesigns_erpbookings_damage as damage_dep', $this->cleansTable . '.booking_id', '=', 'damage_dep.booking_id')
            ->leftJoin('virtualdesigns_operationalinformation_operationalinformation as opinfo', $this->cleansTable . '.property_id', '=', 'opinfo.property_id')
            ->where($this->cleansTable . '.clean_type', '=', 'Departure Clean')
            ->where($this->cleansTable . '.damage_status', '!=', 'Wear and Tear')
            ->where($this->cleansTable . '.damage_status', '!=', 'No Damage')
            ->where($this->cleansTable . '.status', '!=', 1)
            ->where('booking.departure_date', '>=', '2025-10-01')
            ->whereNull($this->cleansTable . '.deleted_at');

        if ((int) $isCompleted === 1) {
            $damageClaims = $damageClaims->where($this->cleansTable . '.damages_processed', '=', 1);
        } else {
            $damageClaims = $damageClaims->whereNull($this->cleansTable . '.damages_processed');
        }

        $damageClaims = $damageClaims->select(
            'booking.id as booking_id',
            'booking.booking_ref',
            'booking.channel',
            'booking.client_name',
            'booking.client_mobile',
            'booking.client_email',
            'booking.arrival_date',
            'booking.departure_date',
            $this->cleansTable . '.damage_status',
            $this->cleansTable . '.damage_amount',
            $this->cleansTable . '.damage_description',
            $this->cleansTable . '.no_guest_charge',
            $this->cleansTable . '.sales_notified',
            $this->cleansTable . '.claim_status',
            'property.name',
            $this->cleansTable . '.id as task_id',
            'booking.bd_active as bd_active',
            'damage_dep.id as damage_dep_id',
            'damage_dep.amount as amount_taken'
        )->get()->unique();

        if ((int) $isCompleted !== 1) {
            $damageClaimsFinal = $damageClaims;
            $damageDepIds = $damageClaims->pluck('damage_dep_id')->filter()->unique();

            if ($damageDepIds->isNotEmpty()) {
                $creditedIds = DB::connection('acclive')
                    ->table('breakage_deposits')
                    ->whereIn('HostAgentsId', $damageDepIds)
                    ->whereNotNull('CreditNoteId')
                    ->pluck('HostAgentsId')
                    ->toArray();

                $damageClaimsFinal = $damageClaims->reject(function ($claim) use ($creditedIds) {
                    return $claim->damage_dep_id && in_array($claim->damage_dep_id, $creditedIds, true);
                })->values();
            }

            foreach ($damageClaimsFinal as $damageClaim) {
                $accBd = DB::connection('acclive')
                    ->table('breakage_deposits')
                    ->where('HostAgentsId', '=', $damageClaim->damage_dep_id)
                    ->first();

                if (isset($accBd->Id)) {
                    $damageClaim->bd_invoice = $accBd->InvoiceId;
                    $damageClaim->in_acc = 'Yes';
                } else {
                    $damageClaim->bd_invoice = null;
                    $damageClaim->in_acc = 'No';
                }
            }

            return $this->corsJson($damageClaimsFinal, 200);
        }

        return $this->corsJson($damageClaims->values(), 200);
    }

    public function updateDamageClaim(Request $request, ?int $id = null): JsonResponse
    {
        $this->assertApiKey($request);

        $damageClaimId = $id ?? (int) $request->input('id');
        if ($damageClaimId <= 0) {
            return $this->corsJson(['code' => 400, 'message' => 'Task id is required'], 400);
        }

        $damageClaim = DB::table($this->cleansTable)->where('id', '=', $damageClaimId)->first();
        if ($damageClaim === null) {
            return $this->corsJson(['code' => 404, 'message' => 'Task not found'], 404);
        }

        $payload = $request->all();
        $changeUser = $payload['change_user'] ?? null;

        $fields = [
            'damage_amount',
            'damage_description',
            'no_guest_charge',
            'sales_notified',
        ];

        $updates = [];
        foreach ($fields as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $newValue = $payload[$field];
            $oldValue = $damageClaim->$field ?? null;

            if ($oldValue != $newValue) {
                $this->logChange($changeUser, $damageClaim->id, $field, $oldValue, $newValue);
            }

            $updates[$field] = $newValue;
        }

        if (!empty($updates)) {
            DB::table($this->cleansTable)->where('id', '=', $damageClaimId)->update($updates);
        }

        $damageClaim = DB::table($this->cleansTable)
            ->leftJoin('virtualdesigns_properties_properties as property', $this->cleansTable . '.property_id', '=', 'property.id')
            ->leftJoin('users as supplier', $this->cleansTable . '.supplier_id', '=', 'supplier.id')
            ->leftJoin('virtualdesigns_erpbookings_erpbookings as booking', $this->cleansTable . '.booking_id', '=', 'booking.id')
            ->leftJoin('virtualdesigns_operationalinformation_operationalinformation as opinfo', $this->cleansTable . '.property_id', '=', 'opinfo.property_id')
            ->where($this->cleansTable . '.id', '=', $damageClaimId)
            ->select(
                'booking.booking_ref',
                'booking.client_name',
                'booking.client_mobile',
                'booking.client_email',
                'booking.arrival_date',
                'booking.departure_date',
                $this->cleansTable . '.damage_status',
                $this->cleansTable . '.damage_amount',
                $this->cleansTable . '.damage_description',
                $this->cleansTable . '.no_guest_charge',
                $this->cleansTable . '.sales_notified',
                'property.name',
                $this->cleansTable . '.id as task_id'
            )
            ->get()
            ->unique();

        $damageClaimRecord = $damageClaim->first();

        event(new ResourceChanged('tasks', 'updated', $damageClaimId, [
            'booking_ref' => $damageClaimRecord->booking_ref ?? null,
        ]));

        return $this->corsJson($damageClaim, 200);
    }

    public function cleanLaundryMaint(): void
    {
        $bookings = DB::table('virtualdesigns_erpbookings_erpbookings')
            ->where('status', '=', 0)
            ->whereNull('deleted_at')
            ->get();

        foreach ($bookings as $booking) {
            $nonMsaCleans = DB::table($this->cleansTable)
                ->where('booking_id', '=', $booking->id)
                ->where('status', '!=', 1)
                ->whereNull('deleted_at')
                ->where('clean_type', '!=', 'MSA')
                ->get();

            foreach ($nonMsaCleans as $nonMsaClean) {
                if ($nonMsaClean->clean_type === 'Arrival Clean' || $nonMsaClean->clean_type === 'Concierge Arrival' || $nonMsaClean->clean_type === 'Welcome Pack') {
                    DB::table($this->cleansTable)->where('id', '=', $nonMsaClean->id)->update([
                        'property_id' => $booking->property_id,
                        'clean_date' => $booking->arrival_date,
                        'status' => $nonMsaClean->status,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }

                if ($nonMsaClean->clean_type === 'Departure Clean' || $nonMsaClean->clean_type === 'Concierge Departure') {
                    DB::table($this->cleansTable)->where('id', '=', $nonMsaClean->id)->update([
                        'property_id' => $booking->property_id,
                        'clean_date' => $booking->departure_date,
                        'status' => $nonMsaClean->status,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            }
        }
    }

    private function pushNotify(int $userId, int $recordId): string
    {
        $userTokens = DB::table('react_user_tokens')->where('user_id', '=', $userId)->get();

        foreach ($userTokens as $userToken) {
            $client = new Client();
            $client->post('https://exp.host/--/api/v2/push/send', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'to' => $userToken->user_token,
                    'title' => 'Task Updated',
                    'body' => 'One of your tasks have been updated',
                    'content-available' => 1,
                    'data' => ['id' => $recordId, 'type' => 'task'],
                ],
            ]);
        }

        return 'success';
    }

    private function isHoliday(string $date): bool
    {
        $dateStr = date('Y-m-d H:i:s', strtotime($date . ' 00:00:00'));
        $holiday = DB::table('virtualdesigns_publicholidays_publicholidays')
            ->where('date', '=', $dateStr)
            ->count();

        return $holiday > 0;
    }

    private function applyTaskSelect(Builder $query): Builder
    {
        return $query->select($this->taskSelectFields());
    }

    private function taskSelectFields(): array
    {
        return [
            $this->cleansTable . '.id as task_id',
            'property.id as prop_id',
            'property.name as prop_name',
            'property.country_id as country_id',
            'booking.booking_ref',
            'booking.quote_confirmed',
            $this->cleansTable . '.clean_type as task_type',
            $this->cleansTable . '.clean_date as task_date',
            'opinfo.welcome_pack_notes',
            'supplier.name as supplier_name',
            'supplier.surname as supplier_surname',
            $this->cleansTable . '.supplier_id as supplier_id',
            $this->cleansTable . '.price',
            $this->cleansTable . '.job_done',
            $this->cleansTable . '.damage_status',
            $this->cleansTable . '.status',
            'booking.hours as booking_hours',
        ];
    }

    private function getUserGroupId(int $userId): ?int
    {
        return DB::table('users_groups')->where('user_id', '=', $userId)->value('user_group_id');
    }

    private function logChange($userId, $recordId, string $field, $oldValue, $newValue): void
    {
        DB::table('virtualdesigns_changes_changes')->insert([
            'user_id' => $userId,
            'db_table' => $this->cleansTable,
            'record_id' => $recordId,
            'field' => $field,
            'old' => $oldValue,
            'new' => $newValue,
            'change_date' => date('Y-m-d H:i:s'),
        ]);
    }

    private function assertApiKey(Request $request): void
    {
        $apiKey = $request->header('key');
        if ($apiKey === null || md5('aiden@virtualdesigns.co.za3d@=kWfmMR') !== $apiKey) {
            throw new HttpResponseException($this->corsJson([
                'code' => 401,
                'message' => 'Wrong API Key',
            ], 401));
        }
    }

    private function corsJson($data, int $status): JsonResponse
    {
        return response()
            ->json($data, $status)
            ->header('Content-Type', 'application.json')
            ->header('Access-Control-Allow-Origin', '*');
    }
}
