<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use GuzzleHttp\Client;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LaundriesController extends Controller
{
    public function index(Request $request)
    {
        $this->assertApiKey($request);

        $userId = (int) $request->header('userid');
        if ($userId <= 0) {
            return $this->corsJson(['code' => 401, 'message' => 'No user ID supplied in header'], 401);
        }

        $groupId = $this->getUserGroupId($userId);
        $bookingId = $request->header('bookingid');
        $supplierId = $request->header('supplierid');
        $dateRange = $request->header('date');
        $stage = $request->header('stage');
        $status = $request->header('status');
        $bookingRef = $request->header('bookingref');
        $propId = $request->header('propid');
        $isMobile = $request->header('ismobile');
        $page = $request->header('page');
        $perPage = $request->header('perpage') ?: 20;

        $laundries = DB::table('virtualdesigns_laundry_laundry');

        if ($groupId === 4) {
            $laundries->where('virtualdesigns_laundry_laundry.supplier_id', '=', $userId)
                ->whereNull('virtualdesigns_laundry_laundry.deleted_at')
                ->where('virtualdesigns_laundry_laundry.status', '!=', 1);
        } else {
            $laundries->whereNull('virtualdesigns_laundry_laundry.deleted_at')
                ->whereNotNull('virtualdesigns_laundry_laundry.supplier_id');
        }

        if ($bookingId) {
            $laundries->where('virtualdesigns_laundry_laundry.booking_id', '=', $bookingId);
        }

        if ($supplierId) {
            $laundries->where('virtualdesigns_laundry_laundry.supplier_id', '=', $supplierId);
        }

        if ($dateRange) {
            $dates = explode(',', $dateRange);
            if (count($dates) === 2) {
                $laundries->where('virtualdesigns_laundry_laundry.action_date', '>=', $dates[0])
                    ->where('virtualdesigns_laundry_laundry.action_date', '<=', $dates[1]);
            }
        } elseif (!$bookingId) {
            $year = (int) date('Y') - 1;
            $laundries->where('virtualdesigns_laundry_laundry.action_date', '>=', $year . '-01-01');
        }

        if ($stage) {
            $laundries->where('virtualdesigns_laundry_laundry.stage', '=', $stage);
        }

        if ($status !== null && $status !== '') {
            $laundries->where('virtualdesigns_laundry_laundry.status', '=', $status);
        }

        if ($propId) {
            $laundries->where('virtualdesigns_laundry_laundry.property_id', '=', $propId);
        }

        if ($bookingRef) {
            $bookingIds = DB::table('virtualdesigns_erpbookings_erpbookings')
                ->where('booking_ref', '=', $bookingRef)
                ->pluck('id');
            $laundries->whereIn('virtualdesigns_laundry_laundry.booking_id', $bookingIds);
        }

        $laundries = $this->applyLaundryJoins($laundries)
            ->where('booking.status', '!=', 1)
            ->where('booking.quote_confirmed', '=', 1)
            ->whereNull('booking.deleted_at');

        if ($userId === 1636 || $userId === 1709) {
            $laundries->where('property.name', 'like', '%Winelands Golf Lodges%');
        }

        $total = $laundries->count();

        if ($isMobile) {
            $pageNumber = (int) ($page ?: 1);
            $offset = ($pageNumber - 1) * (int) $perPage;
            $laundries->skip($offset)->take((int) $perPage);
        }

        $laundries = $laundries->get()->unique();

        $finalLaundry = [];
        $todayDate = strtotime('today midnight');

        foreach ($laundries as $laundry) {
            $laundry->currency = ((int) $laundry->country_id === 846) ? 'Rs' : 'R';

            if ($bookingId) {
                $diff = $todayDate - strtotime($laundry->action_date);
                $hours = $diff / (60 * 60);
                $laundry->is_locked = $hours >= 48;
            }

            $laundry->is_lastminute = ($laundry->booking_hours !== null && $laundry->booking_hours <= 24);
            $finalLaundry[] = (array) $laundry;
        }

        usort($finalLaundry, [$this, 'cmp']);

        if (!empty($finalLaundry)) {
            $finalLaundry[0]['total'] = $total;
            $finalLaundry[0]['currentpage'] = $isMobile ? (int) ($page ?: 1) : 1;
            $finalLaundry[0]['totalpages'] = $isMobile ? (int) ceil($total / (int) $perPage) : 1;
        }

        return $this->corsJson($finalLaundry, 200);
    }

    public function show(Request $request, int $id)
    {
        $this->assertApiKey($request);

        $userId = (int) $request->header('userid');
        if ($userId <= 0) {
            return $this->corsJson(['code' => 401, 'message' => 'No user ID supplied in header'], 401);
        }

        $groupId = $this->getUserGroupId($userId);
        $laundries = DB::table('virtualdesigns_laundry_laundry')
            ->where('virtualdesigns_laundry_laundry.id', '=', $id)
            ->whereNull('virtualdesigns_laundry_laundry.deleted_at');

        if ($groupId === 4) {
            $laundries->where('virtualdesigns_laundry_laundry.supplier_id', '=', $userId)
                ->whereNotNull('virtualdesigns_laundry_laundry.supplier_id');
        } else {
            $laundries->whereNotNull('virtualdesigns_laundry_laundry.supplier_id');
        }

        $laundries = $this->applyLaundryJoins($laundries)->get();

        foreach ($laundries as $laundry) {
            $laundry->currency = ((int) $laundry->country_id === 846) ? 'Rs' : 'R';
        }

        return $this->corsJson($laundries, 200);
    }

    public function update(Request $request, int $id)
    {
        $this->assertApiKey($request);

        $laundry = DB::table('virtualdesigns_laundry_laundry')->where('id', '=', $id)->first();
        if (!$laundry) {
            return $this->corsJson(['code' => 404, 'message' => 'Laundry not found'], 404);
        }

        $stage = $request->input('stage');
        $notes = $request->input('notes');
        $changeUser = $request->input('change_user');

        if ($changeUser) {
            if ($laundry->stage !== $stage) {
                DB::table('virtualdesigns_changes_changes')->insert([
                    'user_id' => $changeUser,
                    'db_table' => 'virtualdesigns_laundry_laundry',
                    'record_id' => $laundry->id,
                    'field' => 'pack_status',
                    'old' => $laundry->stage,
                    'new' => $stage,
                    'change_date' => now(),
                ]);
            }
            if ($laundry->notes !== $notes) {
                DB::table('virtualdesigns_changes_changes')->insert([
                    'user_id' => $changeUser,
                    'db_table' => 'virtualdesigns_laundry_laundry',
                    'record_id' => $laundry->id,
                    'field' => 'notes',
                    'old' => $laundry->notes,
                    'new' => $notes,
                    'change_date' => now(),
                ]);
            }
        }

        DB::table('virtualdesigns_laundry_laundry')
            ->where('id', '=', $id)
            ->update([
                'stage' => $stage,
                'notes' => $notes,
                'updated_at' => now(),
            ]);

        $laundries = $this->applyLaundryJoins(DB::table('virtualdesigns_laundry_laundry')->where('virtualdesigns_laundry_laundry.id', '=', $id))->get();

        foreach ($laundries as $laundryRow) {
            $laundryRow->currency = ((int) $laundryRow->country_id === 846) ? 'Rs' : 'R';
        }

        $this->pushNotify($laundry->supplier_id, $id);

        return $this->corsJson($laundries, 200);
    }

    public function dashboard(Request $request, int $userid)
    {
        $this->assertApiKey($request);

        $propId = $request->header('propid');
        $groupId = $this->getUserGroupId($userid);
        $todayDate = date('Y-m-d');

        $laundry = DB::table('virtualdesigns_laundry_laundry')
            ->whereNull('deleted_at')
            ->where('status', '=', 0)
            ->where('stage', '!=', 'Cancelled')
            ->whereNotNull('supplier_id')
            ->where('action_date', '=', $todayDate);

        if ($groupId !== 2) {
            $laundry->where('supplier_id', '=', $userid);
        } elseif ($userid === 1636 || $userid === 1709) {
            $laundry->where('name', 'like', '%Winelands Golf Lodges%');
        }

        if ($propId) {
            $laundry->where('property_id', '=', $propId);
        }

        $laundryPending = (clone $laundry)->where('stage', '!=', 'Returned')->count();
        $laundryCompleted = (clone $laundry)->where('stage', '=', 'Returned')->count();

        $resp = [
            'laundry_total' => $laundry->count(),
            'laundry_pending' => $laundryPending,
            'laundry_completed' => $laundryCompleted,
        ];

        return $this->corsJson($resp, 200);
    }

    public function pushNotify(?int $userId, int $recId)
    {
        if (!$userId) {
            return 'success';
        }

        $tokens = DB::table('react_user_tokens')->where('user_id', '=', $userId)->get();
        if ($tokens->isEmpty()) {
            return 'success';
        }

        $client = new Client();

        foreach ($tokens as $token) {
            try {
                $client->post('https://exp.host/--/api/v2/push/send', [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json' => [
                        'to' => $token->user_token,
                        'title' => 'Laundry Updated',
                        'body' => 'One of your laundries have been updated',
                        'content-available' => 1,
                        'data' => ['id' => $recId, 'type' => 'laundry'],
                    ],
                ]);
            } catch (\Throwable $e) {
                continue;
            }
        }

        return 'success';
    }

    public function cmp($a, $b)
    {
        return strcmp($b['is_lastminute'] ?? '', $a['is_lastminute'] ?? '');
    }

    private function applyLaundryJoins($query)
    {
        return $query
            ->leftJoin('virtualdesigns_properties_properties as property', 'virtualdesigns_laundry_laundry.property_id', '=', 'property.id')
            ->leftJoin('virtualdesigns_extracharges_extracharges as extras', 'virtualdesigns_laundry_laundry.property_id', '=', 'extras.property_id')
            ->leftJoin('users as supplier', 'virtualdesigns_laundry_laundry.supplier_id', '=', 'supplier.id')
            ->leftJoin('users as manager', 'property.user_id', '=', 'manager.id')
            ->leftJoin('virtualdesigns_erpbookings_erpbookings as booking', 'virtualdesigns_laundry_laundry.booking_id', '=', 'booking.id')
            ->leftJoin('virtualdesigns_propertylinen_beds as bed_rec', 'virtualdesigns_laundry_laundry.property_id', '=', 'bed_rec.property_id')
            ->select(
                'virtualdesigns_laundry_laundry.id',
                'extras.fanote_prices',
                'property.name as property_name',
                'property.country_id as country_id',
                'booking.room_name',
                'booking.booking_ref',
                'booking.arrival_date',
                'booking.departure_date',
                'booking.quote_confirmed',
                'booking.hours',
                'virtualdesigns_laundry_laundry.stage',
                'virtualdesigns_laundry_laundry.stage',
                'supplier.name as supplier_name',
                'supplier.surname as supplier_surname',
                'manager.name as manager_name',
                'manager.surname as manager_surname',
                'virtualdesigns_laundry_laundry.action_date',
                'virtualdesigns_laundry_laundry.notes',
                'virtualdesigns_laundry_laundry.supplier_id as supplier_id',
                'virtualdesigns_laundry_laundry.status as status',
                'property.id as propid',
                'booking.hours as booking_hours'
            );
    }

    private function getUserGroupId(int $userId): ?int
    {
        return DB::table('users_groups')->where('user_id', '=', $userId)->value('user_group_id');
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

    private function corsJson($data, int $status)
    {
        return response()
            ->json($data, $status)
            ->header('Content-Type', 'application.json')
            ->header('Access-Control-Allow-Origin', '*');
    }
}
