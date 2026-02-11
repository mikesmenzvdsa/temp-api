<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    public function getTurnover(Request $request)
    {
        $this->assertApiKey($request);

        $userId = $request->header('userid');
        $month = (int) $request->header('month');
        $year = (int) $request->header('year');
        $locId = $request->header('locid', 'all');
        $bedrooms = $request->header('bedrooms', 'all');
        $propIdHeader = $request->header('propid', 'all');

        if ($userId === null || $month <= 0 || $year <= 0) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing required headers'], 400);
        }

        $user = DB::table('users')->where('id', '=', $userId)->first();
        if (!$user) {
            return $this->corsJson(['code' => 404, 'message' => 'User not found'], 404);
        }

        $groupId = DB::table('users_groups')->where('user_id', '=', $userId)->value('user_group_id');
        if ($groupId === null) {
            $groupId = 2;
        }

        if ($propIdHeader !== 'all') {
            $propIds = array_filter(explode(',', (string) $propIdHeader));
            $propertyRecs = DB::table('virtualdesigns_properties_properties')
                ->whereIn('id', $propIds)
                ->select('id', 'name', 'date_activated', 'date_deactivated', 'country_id')
                ->get();
        } else {
            $propertyQuery = DB::table('virtualdesigns_properties_properties')
                ->whereNull('deleted_at')
                ->whereNotNull('date_activated');

            if ($locId !== 'all') {
                $propertyQuery->where('suburb_id', '=', $locId);
            }
            if ($bedrooms !== 'all') {
                $propertyQuery->where('bedroom_num', '=', $bedrooms);
            }

            if ((int) $groupId === 1) {
                $propertyQuery->where('owner_id', '=', $userId);
            } elseif ((int) $groupId === 3) {
                $propertyQuery->where('user_id', '=', $userId);
            } elseif ((int) $groupId === 5) {
                $propertyQuery->where('bodycorp_id', '=', $userId);
            }

            if ((int) $userId === 1636 || (int) $userId === 1709) {
                $propertyQuery->where('name', 'like', '%Winelands Golf Lodges%');
            }

            $propertyRecs = $propertyQuery
                ->select('id', 'name', 'date_activated', 'date_deactivated', 'country_id')
                ->get();
        }

        if ($propertyRecs->isEmpty()) {
            return $this->corsJson('No properties matching applied filters', 404);
        }

        $skipSales = $this->shouldSkipSales($request);
        $monthsArray = [];
        $propCount = 0;

        foreach ($propertyRecs as $propertyRec) {
            $currency = $this->getCurrency((int) $propertyRec->country_id);

            for ($i = 0; $i < 12; $i++) {
                $start = date('Y-m-d', strtotime($year . '-' . $month . '-01' . ' + ' . $i . ' months'));
                $end = date('Y-m-t', strtotime($start));

                $monthsArray[$propCount][$i] = [
                    'start' => $start,
                    'end' => $end,
                    'month' => date('M', strtotime($start)),
                    'year' => date('Y', strtotime($start)),
                    'DateActivated' => $propertyRec->date_activated,
                    'DateDeactivated' => $propertyRec->date_deactivated,
                    'stats' => [],
                ];
            }

            $totalIncome = 0.0;
            $availMonthCount = 12;

            for ($i = 0; $i < 12; $i++) {
                $statsMonth = $monthsArray[$propCount][$i];
                $bookings = DB::table('virtualdesigns_erpbookings_erpbookings')
                    ->whereNull('deleted_at')
                    ->where('status', '=', 0)
                    ->where('quote_confirmed', '=', 1)
                    ->where('so_type', '=', 'booking')
                    ->where('arrival_date', '<=', $statsMonth['end'])
                    ->where('arrival_date', '>=', $statsMonth['start'])
                    ->where('property_id', '=', $propertyRec->id)
                    ->select('id', 'arrival_date', 'departure_date', 'booking_amount', 'total_rands')
                    ->get();

                $monthIncome = 0.0;

                foreach ($bookings as $booking) {
                    $bookingIncome = 0.0;

                    if ($skipSales) {
                        $bookingIncome = (float) ($booking->booking_amount ?? 0);
                    } else {
                        try {
                            $accConnection = $this->getAccConnection((int) $propertyRec->country_id);
                            $salesBooking = DB::connection($accConnection)
                                ->table('bookings')
                                ->where('HostAgentsId', '=', $booking->id)
                                ->where('Status', '=', 0)
                                ->whereNull('DeletedAt')
                                ->first();

                            if ($salesBooking && isset($salesBooking->Id)) {
                                if ((int) $groupId === 1) {
                                    $lineItems = DB::connection($accConnection)
                                        ->table('booking_line_items')
                                        ->where('BookingId', '=', $salesBooking->Id)
                                        ->whereNull('DeletedAt')
                                        ->get();

                                    foreach ($lineItems as $lineItem) {
                                        if ($lineItem->Name === '[' . $propertyRec->id . '] ' . $propertyRec->name) {
                                            $bookingIncome += (float) $lineItem->LineAmount
                                                - ((float) $salesBooking->HostAgentsCommAmount
                                                    + (float) $salesBooking->ThirdPartyCommAmount
                                                    + (float) $salesBooking->HaLevyAmount);
                                        }
                                    }
                                } else {
                                    $bookingIncome = (float) $salesBooking->HostAgentsCommAmount;
                                }
                            }
                        } catch (\Throwable $th) {
                            $bookingIncome = (float) ($booking->booking_amount ?? 0);
                        }
                    }

                    $monthIncome += $bookingIncome;
                }

                if ($monthIncome == 0.0 && $propertyRec->date_activated !== null
                    && strtotime((string) $propertyRec->date_activated) > strtotime($statsMonth['start'])) {
                    $monthsArray[$propCount][$i]['stats']['MonthIncome'] = 'n/a';
                } else {
                    $monthsArray[$propCount][$i]['stats']['MonthIncome'] = round($monthIncome, 2);
                }

                $monthsArray[$propCount][$i]['currency'] = $currency;
                $totalIncome += $monthIncome;
            }

            $monthsArray[$propCount][0]['propID'] = $propertyRec->id;
            $monthsArray[$propCount][0]['propName'] = $propertyRec->name;
            $monthsArray[$propCount][0]['TotalIncome_Year'] = round($totalIncome, 2);
            $monthsArray[$propCount][0]['AvgIncome_Year'] = $availMonthCount > 0
                ? round($totalIncome / $availMonthCount, 2)
                : 0;

            $propCount++;
        }

        return $this->corsJson($monthsArray, 200);
    }

    public function getOccupancy(Request $request)
    {
        $this->assertApiKey($request);

        $userId = $request->header('userid');
        $month = (int) $request->header('month');
        $year = (int) $request->header('year');
        $locId = $request->header('locid', 'all');
        $bedrooms = $request->header('bedrooms', 'all');
        $propIdHeader = $request->header('propid', 'all');

        if ($userId === null || $month <= 0 || $year <= 0) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing required headers'], 400);
        }

        // Build property list (similar to getTurnover)
        if ($propIdHeader !== 'all') {
            $propIds = array_filter(explode(',', (string) $propIdHeader));
            $propertyRecs = DB::table('virtualdesigns_properties_properties')
                ->whereIn('id', $propIds)
                ->select('id', 'name', 'date_activated', 'date_deactivated', 'country_id', 'capacity')
                ->get();
        } else {
            $propertyQuery = DB::table('virtualdesigns_properties_properties')
                ->whereNull('deleted_at')
                ->whereNotNull('date_activated')
                ->where('is_live', '=', 1)
                ->where('exclude_occupancy', '!=', 1);

            if ($locId !== 'all') {
                $propertyQuery->where('suburb_id', '=', $locId);
            }
            if ($bedrooms !== 'all') {
                $propertyQuery->where('bedroom_num', '=', $bedrooms);
            }

            $propertyRecs = $propertyQuery
                ->select('id', 'name', 'date_activated', 'date_deactivated', 'country_id', 'capacity')
                ->get();
        }

        if ($propertyRecs->isEmpty()) {
            return $this->corsJson('No properties matching applied filters', 404);
        }

        $monthsArray = [];
        $propCount = 0;

        foreach ($propertyRecs as $propertyRec) {
            for ($i = 0; $i < 12; $i++) {
                $start = date('Y-m-d', strtotime($year . '-' . $month . '-01' . ' + ' . $i . ' months'));
                $end = date('Y-m-t', strtotime($start));

                $monthsArray[$propCount][$i] = [
                    'start' => $start,
                    'end' => $end,
                    'month' => date('M', strtotime($start)),
                    'year' => date('Y', strtotime($start)),
                    'DateActivated' => $propertyRec->date_activated,
                    'DateDeactivated' => $propertyRec->date_deactivated,
                    'stats' => [],
                ];
            }

            $occupancies = [];

            for ($i = 0; $i < 12; $i++) {
                $statsMonth = $monthsArray[$propCount][$i];

                $bookings = DB::table('virtualdesigns_erpbookings_erpbookings')
                    ->whereNull('deleted_at')
                    ->where('status', '=', 0)
                    ->where('quote_confirmed', '=', 1)
                    ->where('so_type', '=', 'booking')
                    ->where('arrival_date', '<=', $statsMonth['end'])
                    ->where('departure_date', '>=', $statsMonth['start'])
                    ->where('property_id', '=', $propertyRec->id)
                    ->select('id', 'arrival_date', 'departure_date')
                    ->get();

                $bookedNights = 0;
                foreach ($bookings as $booking) {
                    $s = max(strtotime($booking->arrival_date), strtotime($statsMonth['start']));
                    $e = min(strtotime($booking->departure_date), strtotime($statsMonth['end']));
                    $nights = max(0, (int) round(($e - $s) / 86400));
                    $bookedNights += $nights;
                }

                $daysInMonth = (int) date('t', strtotime($statsMonth['start']));
                $capacity = isset($propertyRec->capacity) && (int) $propertyRec->capacity > 0 ? (int) $propertyRec->capacity : 1;

                $possibleNights = $daysInMonth * $capacity;
                $occupancyPercent = $possibleNights > 0 ? ($bookedNights / $possibleNights) * 100 : 0;

                $monthsArray[$propCount][$i]['stats']['Occupancy'] = round($occupancyPercent, 0);
                $occupancies[] = $monthsArray[$propCount][$i]['stats']['Occupancy'];
            }

            $avg = count($occupancies) ? array_sum($occupancies) / count($occupancies) : 0;
            $monthsArray[$propCount][0]['propID'] = $propertyRec->id;
            $monthsArray[$propCount][0]['propName'] = $propertyRec->name;
            $monthsArray[$propCount][0]['AvgOccupancy'] = round($avg, 0);

            $propCount++;
        }

        return $this->corsJson($monthsArray, 200);
    }

    public function getNights(Request $request)
    {
        $this->assertApiKey($request);

        $userId = $request->header('userid');
        $month = (int) $request->header('month');
        $year = (int) $request->header('year');
        $locId = $request->header('locid', 'all');
        $bedrooms = $request->header('bedrooms', 'all');
        $propIdHeader = $request->header('propid', 'all');

        if ($userId === null || $month <= 0 || $year <= 0) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing required headers'], 400);
        }

        // Build property list
        if ($propIdHeader !== 'all') {
            $propIds = array_filter(explode(',', (string) $propIdHeader));
            $propertyRecs = DB::table('virtualdesigns_properties_properties')
                ->whereIn('id', $propIds)
                ->select('id', 'name', 'date_activated', 'date_deactivated', 'country_id', 'capacity')
                ->get();
        } else {
            $propertyQuery = DB::table('virtualdesigns_properties_properties')
                ->whereNull('deleted_at')
                ->whereNotNull('date_activated')
                ->where('is_live', '=', 1);

            if ($locId !== 'all') {
                $propertyQuery->where('suburb_id', '=', $locId);
            }
            if ($bedrooms !== 'all') {
                $propertyQuery->where('bedroom_num', '=', $bedrooms);
            }

            $propertyRecs = $propertyQuery
                ->select('id', 'name', 'date_activated', 'date_deactivated', 'country_id', 'capacity')
                ->get();
        }

        if ($propertyRecs->isEmpty()) {
            return $this->corsJson('No properties matching applied filters', 404);
        }

        $monthsArray = [];
        $propCount = 0;

        foreach ($propertyRecs as $propertyRec) {
            $totalNights = 0;

            for ($i = 0; $i < 12; $i++) {
                $start = date('Y-m-d', strtotime($year . '-' . $month . '-01' . ' + ' . $i . ' months'));
                $end = date('Y-m-t', strtotime($start));

                $monthsArray[$propCount][$i] = [
                    'start' => $start,
                    'end' => $end,
                    'month' => date('M', strtotime($start)),
                    'year' => date('Y', strtotime($start)),
                    'DateActivated' => $propertyRec->date_activated,
                    'DateDeactivated' => $propertyRec->date_deactivated,
                    'stats' => [],
                ];

                $bookings = DB::table('virtualdesigns_erpbookings_erpbookings')
                    ->whereNull('deleted_at')
                    ->where('status', '=', 0)
                    ->where('quote_confirmed', '=', 1)
                    ->where('so_type', '=', 'booking')
                    ->where('arrival_date', '<=', $end)
                    ->where('departure_date', '>=', $start)
                    ->where('property_id', '=', $propertyRec->id)
                    ->select('id', 'arrival_date', 'departure_date')
                    ->get();

                $bookedNights = 0;
                foreach ($bookings as $booking) {
                    $s = max(strtotime($booking->arrival_date), strtotime($start));
                    $e = min(strtotime($booking->departure_date), strtotime($end));
                    $nights = max(0, (int) round(($e - $s) / 86400));
                    $bookedNights += $nights;
                }

                $monthsArray[$propCount][$i]['stats']['NightsBooked'] = $bookedNights;
                $totalNights += $bookedNights;
            }

            $avg = $totalNights > 0 ? ($totalNights / 12) : 0;
            $monthsArray[$propCount][0]['propID'] = $propertyRec->id;
            $monthsArray[$propCount][0]['propName'] = $propertyRec->name;
            $monthsArray[$propCount][0]['TotalNights'] = $totalNights;
            $monthsArray[$propCount][0]['AvgNights'] = round($avg, 0);

            $propCount++;
        }

        return $this->corsJson($monthsArray, 200);
    }

    private function getAccConnection(int $countryId): string
    {
        if ($countryId === 846) {
            return 'acctest';
        }
        if ($countryId === 854) {
            return 'accuae';
        }

        return 'acclive';
    }

    private function getCurrency(int $countryId): string
    {
        if ($countryId === 846) {
            return 'Rs';
        }
        if ($countryId === 854) {
            return 'AED';
        }

        return 'R';
    }

    private function shouldSkipSales(Request $request): bool
    {
        $headerValue = strtolower((string) $request->header('skip-sales', ''));
        if ($headerValue === 'true' || $headerValue === '1') {
            return true;
        }

        return filter_var(env('DASHBOARD_SKIP_SALES', false), FILTER_VALIDATE_BOOL);
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
