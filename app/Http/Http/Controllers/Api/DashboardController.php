<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use DateTime;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    private function calculateTotalNightsByMonth($bookings, int $diff, string $startdate, string $enddate): array
    {
        $startMonth = (int) date('n', strtotime($startdate));
        $nightsByMonth = array_fill($startMonth, $diff, 0);

        foreach ($bookings as $booking) {
            $start = new DateTime($booking->arrival_date);
            $end = new DateTime($booking->departure_date);
            $end->modify('-1 day');

            $currentYear = (int) date('Y', strtotime($startdate));
            while ($start <= $end) {
                $year = (int) $start->format('Y');
                if ($year === $currentYear) {
                    $month = (int) $start->format('n');
                    if (isset($nightsByMonth[$month])) {
                        $nightsByMonth[$month]++;
                    }
                }
                $start->modify('+1 day');
            }
        }

        return $nightsByMonth;
    }

    public function index(Request $request)
    {
        $this->assertApiKey($request);

        $userId = $request->header('userid') ?? $request->header('Userid');
        $propId = $request->header('propid') ?? $request->header('Propid');
        $startdate = $request->header('startdate') ?? $request->header('Startdate');
        $enddate = $request->header('enddate') ?? $request->header('Enddate');
        $country = $request->header('country') ?? $request->header('Country') ?? 'ZA';

        if (!$userId || !$startdate || !$enddate) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing required headers'], 400);
        }

        $user = DB::table('users')->where('id', '=', $userId)->first();
        if (!$user) {
            return $this->corsJson(['code' => 404, 'message' => 'User not found'], 404);
        }

        $group = DB::table('users_groups')
            ->where('user_id', '=', $userId)
            ->orderBy('user_group_id')
            ->first();

        $groupId = $group ? (int) $group->user_group_id : null;

        $currency = 'R';
        $propertyIds = [];

        if ($groupId === 2) {
            if ((int) $propId > 0) {
                $propCountry = DB::table('virtualdesigns_properties_properties')
                    ->where('is_live', '=', 1)
                    ->where('exclude_occupancy', '!=', 1)
                    ->where('id', '=', $propId)
                    ->first();

                if ($propCountry && (int) $propCountry->country_id === 846) {
                    $currency = 'Rs';
                } elseif ($propCountry && (int) $propCountry->country_id === 854) {
                    $currency = 'AED';
                }

                $propertyIds = DB::table('virtualdesigns_properties_properties')
                    ->where('is_live', '=', 1)
                    ->where('exclude_occupancy', '!=', 1)
                    ->where('id', '=', $propId)
                    ->pluck('id')
                    ->toArray();
            } else {
                if ($country === 'MU') {
                    $currency = 'Rs';
                    $propertyIds = DB::table('virtualdesigns_properties_properties')
                        ->where('is_live', '=', 1)
                        ->where('exclude_occupancy', '!=', 1)
                        ->where('country_id', '=', 846)
                        ->pluck('id')
                        ->toArray();
                } elseif ($country === 'UAE') {
                    $currency = 'AED';
                    $propertyIds = DB::table('virtualdesigns_properties_properties')
                        ->where('is_live', '=', 1)
                        ->where('exclude_occupancy', '!=', 1)
                        ->where('country_id', '=', 854)
                        ->pluck('id')
                        ->toArray();
                } else {
                    $currency = 'R';
                    $propertyIds = DB::table('virtualdesigns_properties_properties')
                        ->where('is_live', '=', 1)
                        ->where('exclude_occupancy', '!=', 1)
                        ->where('country_id', '!=', 846)
                        ->where('country_id', '!=', 854)
                        ->pluck('id')
                        ->toArray();
                }
            }
        } elseif ($groupId === 1) {
            if ((int) $propId > 0) {
                $propCountry = DB::table('virtualdesigns_properties_properties')
                    ->where('is_live', '=', 1)
                    ->where('id', '=', $propId)
                    ->where('owner_id', '=', $userId)
                    ->where('exclude_occupancy', '!=', 1)
                    ->first();

                if ($propCountry && (int) $propCountry->country_id === 846) {
                    $currency = 'Rs';
                } elseif ($propCountry && (int) $propCountry->country_id === 854) {
                    $currency = 'AED';
                }

                $propertyIds = DB::table('virtualdesigns_properties_properties')
                    ->where('is_live', '=', 1)
                    ->where('id', '=', $propId)
                    ->where('owner_id', '=', $userId)
                    ->where('exclude_occupancy', '!=', 1)
                    ->pluck('id')
                    ->toArray();
            } else {
                $propCountry = DB::table('virtualdesigns_properties_properties')
                    ->where('is_live', '=', 1)
                    ->where('owner_id', '=', $userId)
                    ->where('exclude_occupancy', '!=', 1)
                    ->first();

                if ($propCountry && (int) $propCountry->country_id === 846) {
                    $currency = 'Rs';
                } elseif ($propCountry && (int) $propCountry->country_id === 854) {
                    $currency = 'AED';
                }

                $propertyIds = DB::table('virtualdesigns_properties_properties')
                    ->where('is_live', '=', 1)
                    ->where('owner_id', '=', $userId)
                    ->where('exclude_occupancy', '!=', 1)
                    ->pluck('id')
                    ->toArray();
            }
        } else {
            $propertyIds = DB::table('virtualdesigns_properties_properties')
                ->where('is_live', '=', 1)
                ->where('exclude_occupancy', '!=', 1)
                ->pluck('id')
                ->toArray();
        }

        if (empty($propertyIds)) {
            return $this->corsJson(['code' => 404, 'message' => 'No properties found'], 404);
        }

        $occReduc = 0;

        $ownerBookings = ((int) $propId > 0)
            ? DB::table('virtualdesigns_erpbookings_erpbookings')
                ->where('property_id', '=', $propId)
                ->where('departure_date', '>=', $startdate)
                ->where('arrival_date', '<=', $enddate)
                ->where('quote_confirmed', '=', 1)
                ->where('status', '=', 0)
                ->whereNull('deleted_at')
                ->where('channel', '=', 'Owner')
                ->get()
            : DB::table('virtualdesigns_erpbookings_erpbookings')
                ->whereIn('property_id', $propertyIds)
                ->where('departure_date', '>=', $startdate)
                ->where('arrival_date', '<=', $enddate)
                ->where('quote_confirmed', '=', 1)
                ->where('status', '=', 0)
                ->whereNull('deleted_at')
                ->where('channel', '=', 'Owner')
                ->get();

        $ownerNights = 0;
        foreach ($ownerBookings as $ownerBooking) {
            $startTs = strtotime($ownerBooking->arrival_date < $startdate ? $startdate : $ownerBooking->arrival_date);
            $endTs = strtotime($ownerBooking->departure_date > $enddate ? $enddate : $ownerBooking->departure_date);
            $ownerNights += (int) round(($endTs - $startTs) / 86400);
        }

        $blockBookings = ((int) $propId > 0)
            ? DB::table('virtualdesigns_erpbookings_erpbookings')
                ->where('property_id', '=', $propId)
                ->where('departure_date', '>=', $startdate)
                ->where('arrival_date', '<=', $enddate)
                ->where('status', '=', 0)
                ->whereNull('deleted_at')
                ->where('so_type', '=', 'block')
                ->get()
            : DB::table('virtualdesigns_erpbookings_erpbookings')
                ->whereIn('property_id', $propertyIds)
                ->where('departure_date', '>=', $startdate)
                ->where('arrival_date', '<=', $enddate)
                ->where('status', '=', 0)
                ->whereNull('deleted_at')
                ->where('so_type', '=', 'block')
                ->get();

        $blockNights = 0;
        foreach ($blockBookings as $blockBooking) {
            $startTs = strtotime($blockBooking->arrival_date < $startdate ? $startdate : $blockBooking->arrival_date);
            $endTs = strtotime($blockBooking->departure_date > $enddate ? $enddate : $blockBooking->departure_date);
            $blockNights += (int) round(($endTs - $startTs) / 86400);
        }

        $maintenanceBookings = ((int) $propId > 0)
            ? DB::table('virtualdesigns_erpbookings_erpbookings')
                ->where('property_id', '=', $propId)
                ->where('departure_date', '>=', $startdate)
                ->where('arrival_date', '<=', $enddate)
                ->where('status', '=', 0)
                ->whereNull('deleted_at')
                ->where('so_type', '=', 'maintenance')
                ->get()
            : DB::table('virtualdesigns_erpbookings_erpbookings')
                ->whereIn('property_id', $propertyIds)
                ->where('departure_date', '>=', $startdate)
                ->where('arrival_date', '<=', $enddate)
                ->where('status', '=', 0)
                ->whereNull('deleted_at')
                ->where('so_type', '=', 'maintenance')
                ->get();

        $maintenanceNights = 0;
        foreach ($maintenanceBookings as $maintenanceBooking) {
            $startTs = strtotime($maintenanceBooking->arrival_date < $startdate ? $startdate : $maintenanceBooking->arrival_date);
            $endTs = strtotime($maintenanceBooking->departure_date > $enddate ? $enddate : $maintenanceBooking->departure_date);
            $maintenanceNights += (int) round(($endTs - $startTs) / 86400);
        }

        $photoShootBookings = ((int) $propId > 0)
            ? DB::table('virtualdesigns_erpbookings_erpbookings')
                ->where('property_id', '=', $propId)
                ->where('departure_date', '>=', $startdate)
                ->where('arrival_date', '<=', $enddate)
                ->where('status', '=', 0)
                ->whereNull('deleted_at')
                ->where('so_type', '=', 'photo_shoot')
                ->get()
            : DB::table('virtualdesigns_erpbookings_erpbookings')
                ->whereIn('property_id', $propertyIds)
                ->where('departure_date', '>=', $startdate)
                ->where('arrival_date', '<=', $enddate)
                ->where('status', '=', 0)
                ->whereNull('deleted_at')
                ->where('so_type', '=', 'photo_shoot')
                ->get();

        $photoShootNights = 0;
        foreach ($photoShootBookings as $photoShootBooking) {
            $startTs = strtotime($photoShootBooking->arrival_date < $startdate ? $startdate : $photoShootBooking->arrival_date);
            $endTs = strtotime($photoShootBooking->departure_date > $enddate ? $enddate : $photoShootBooking->departure_date);
            $photoShootNights += (int) round(($endTs - $startTs) / 86400);
        }

        $startTs = strtotime($startdate);
        $endTs = strtotime($enddate);
        $daysInYear = (int) round(($endTs - $startTs) / 86400);
        $availDays = ($daysInYear * count($propertyIds)) + 1;
        $availDays = $availDays - $occReduc - $ownerNights - $blockNights - $maintenanceNights - $photoShootNights;

        $allBookings = ((int) $propId > 0)
            ? DB::table('virtualdesigns_erpbookings_erpbookings')
                ->where('property_id', '=', $propId)
                ->where('departure_date', '>=', $startdate)
                ->where('arrival_date', '<=', $enddate)
                ->where('quote_confirmed', '=', 1)
                ->where('status', '=', 0)
                ->whereNull('deleted_at')
                ->where('channel', '!=', 'Owner')
                ->where('so_type', '!=', 'block')
                ->where('so_type', '!=', 'maintenance')
                ->where('so_type', '!=', 'photo_shoot')
                ->where('so_type', '!=', 'owner_booking')
                ->get()
            : DB::table('virtualdesigns_erpbookings_erpbookings')
                ->whereIn('property_id', $propertyIds)
                ->where('departure_date', '>=', $startdate)
                ->where('arrival_date', '<=', $enddate)
                ->where('quote_confirmed', '=', 1)
                ->where('status', '=', 0)
                ->whereNull('deleted_at')
                ->where('channel', '!=', 'Owner')
                ->where('so_type', '!=', 'block')
                ->where('so_type', '!=', 'maintenance')
                ->where('so_type', '!=', 'photo_shoot')
                ->where('so_type', '!=', 'owner_booking')
                ->get();

        $time1 = strtotime($startdate);
        $time2 = strtotime($enddate);
        $year1 = (int) date('Y', $time1);
        $month1 = (int) date('m', $time1);
        $year2 = (int) date('Y', $time2);
        $month2 = (int) date('m', $time2);
        $months = (($year2 - $year1) * 12) + ($month2 - $month1);
        $diff = $months + 1;

        $totalNightsByMonth = $this->calculateTotalNightsByMonth($allBookings, $diff, $startdate, $enddate);

        $daysBooked = 0;
        foreach ($totalNightsByMonth as $totalNights) {
            $daysBooked += $totalNights;
        }

        $occu = $availDays > 0 ? ($daysBooked / $availDays) * 100 : 0;
        $allTotalBookings = count($allBookings);
        $avgStay = $daysBooked > 0 ? $daysBooked / $allTotalBookings : 0;

        $skipSales = filter_var(env('DASHBOARD_SKIP_SALES', false), FILTER_VALIDATE_BOOLEAN)
            || strtolower((string) $request->header('skip-sales', 'false')) === 'true';

        if ($skipSales) {
            $salesBookings = collect();
        } elseif ($currency === 'Rs') {
            $salesBookings = ((int) $propId > 0)
                ? DB::connection('acctest')->table('bookings')
                    ->where('PropertyId', '=', $propId)
                    ->where('ArrivalDate', '>=', $startdate)
                    ->where('ArrivalDate', '<=', $enddate)
                    ->where('Status', '=', 0)
                    ->whereNull('DeletedAt')
                    ->where('channel', '!=', 'Owner')
                    ->where('channel', '!=', 'Block')
                    ->where('channel', '!=', 'Maintenance')
                    ->get()
                : DB::connection('acctest')->table('bookings')
                    ->whereIn('PropertyId', $propertyIds)
                    ->where('ArrivalDate', '>=', $startdate)
                    ->where('ArrivalDate', '<=', $enddate)
                    ->where('Status', '=', 0)
                    ->whereNull('DeletedAt')
                    ->where('channel', '!=', 'Owner')
                    ->where('channel', '!=', 'Block')
                    ->where('channel', '!=', 'Maintenance')
                    ->where('channel', '!=', 'Photo Shoot')
                    ->get();
        } elseif ($currency === 'AED') {
            $salesBookings = ((int) $propId > 0)
                ? DB::connection('accuae')->table('bookings')
                    ->where('PropertyId', '=', $propId)
                    ->where('ArrivalDate', '>=', $startdate)
                    ->where('ArrivalDate', '<=', $enddate)
                    ->where('Status', '=', 0)
                    ->whereNull('DeletedAt')
                    ->where('channel', '!=', 'Owner')
                    ->where('channel', '!=', 'Block')
                    ->where('channel', '!=', 'Maintenance')
                    ->get()
                : DB::connection('accuae')->table('bookings')
                    ->whereIn('PropertyId', $propertyIds)
                    ->where('ArrivalDate', '>=', $startdate)
                    ->where('ArrivalDate', '<=', $enddate)
                    ->where('Status', '=', 0)
                    ->whereNull('DeletedAt')
                    ->where('channel', '!=', 'Owner')
                    ->where('channel', '!=', 'Block')
                    ->where('channel', '!=', 'Maintenance')
                    ->where('channel', '!=', 'Photo Shoot')
                    ->get();
        } else {
            $salesBookings = ((int) $propId > 0)
                ? DB::connection('acclive')->table('bookings')
                    ->where('PropertyId', '=', $propId)
                    ->where('ArrivalDate', '>=', $startdate)
                    ->where('ArrivalDate', '<=', $enddate)
                    ->where('Status', '=', 0)
                    ->whereNull('DeletedAt')
                    ->where('channel', '!=', 'Owner')
                    ->where('channel', '!=', 'Block')
                    ->where('channel', '!=', 'Maintenance')
                    ->get()
                : DB::connection('acclive')->table('bookings')
                    ->whereIn('PropertyId', $propertyIds)
                    ->where('ArrivalDate', '>=', $startdate)
                    ->where('ArrivalDate', '<=', $enddate)
                    ->where('Status', '=', 0)
                    ->whereNull('DeletedAt')
                    ->where('channel', '!=', 'Owner')
                    ->where('channel', '!=', 'Block')
                    ->where('channel', '!=', 'Maintenance')
                    ->where('channel', '!=', 'Photo Shoot')
                    ->get();
        }

        $totalSales = 0.0;
        $totalSalesGross = 0.0;
        $graphNet = [];
        $graphGross = [];
        $totalBookings = 0;
        $channelBookings = [];

        foreach ($allBookings as $booking) {
            if ($booking->arrival_date >= $startdate && $booking->arrival_date <= $enddate) {
                $totalBookings += 1;
                $channel = $booking->channel ?? 'Unknown';
                if (!isset($channelBookings[$channel])) {
                    $channelBookings[$channel] = ['Name' => $channel, 'Amount' => 1];
                } else {
                    $channelBookings[$channel]['Amount'] += 1;
                }
            }
        }

        $startMonth = (int) date('n', strtotime($startdate));
        $graphData = array_fill($startMonth, $diff, []);

        foreach ($salesBookings as $salesBooking) {
            $propertyRec = DB::table('virtualdesigns_properties_properties')
                ->where('id', '=', $salesBooking->PropertyId)
                ->first();

            if (!$propertyRec) {
                continue;
            }

            $amount = 0.0;
            $amountGross = 0.0;

            if ($groupId === 2) {
                if ((float) $salesBooking->HostAgentsCommAmount > 0) {
                    $totalSales += (float) $salesBooking->HostAgentsCommAmount;
                    $amount = (float) $salesBooking->HostAgentsCommAmount;
                }
                $totalSalesGross += (float) $salesBooking->Amount;
                $amountGross = (float) $salesBooking->Amount;
            }

            if ($groupId === 1) {
                if ($currency === 'Rs') {
                    $lineItems = DB::connection('acctest')->table('booking_line_items')
                        ->where('BookingId', '=', $salesBooking->Id)
                        ->whereNull('DeletedAt')
                        ->get();
                } elseif ($currency === 'AED') {
                    $lineItems = DB::connection('accuae')->table('booking_line_items')
                        ->where('BookingId', '=', $salesBooking->Id)
                        ->whereNull('DeletedAt')
                        ->get();
                } else {
                    $lineItems = DB::connection('acclive')->table('booking_line_items')
                        ->where('BookingId', '=', $salesBooking->Id)
                        ->whereNull('DeletedAt')
                        ->get();
                }

                foreach ($lineItems as $lineItem) {
                    $expectedName = '[' . $propertyRec->id . '] ' . $propertyRec->accounting_name;
                    if ($lineItem->Name === $expectedName) {
                        $amount = (float) $lineItem->LineAmount
                            - ((float) $salesBooking->HostAgentsCommAmount
                                + (float) $salesBooking->ThirdPartyCommAmount
                                + (float) $salesBooking->HaLevyAmount);
                        $totalSales += $amount;
                    }
                }
            }

            $bookingMonth = date('m', strtotime($salesBooking->ArrivalDate));
            $monthIndex = (int) $bookingMonth;

            $graphNet[$bookingMonth] = isset($graphNet[$bookingMonth])
                ? round($graphNet[$bookingMonth], 2) + round($amount, 2)
                : round($amount, 2);

            $graphData[$monthIndex]['revenue_net'] = isset($graphData[$monthIndex]['revenue_net'])
                ? round($graphData[$monthIndex]['revenue_net'], 2) + round($amount, 2)
                : round($amount, 2);

            $graphGross[$bookingMonth] = isset($graphGross[$bookingMonth])
                ? round($graphGross[$bookingMonth], 2) + round($amountGross, 2)
                : round($amountGross, 2);

            $graphData[$monthIndex]['revenue_gross'] = isset($graphData[$monthIndex]['revenue_gross'])
                ? round($graphData[$monthIndex]['revenue_gross'], 2) + round($amountGross, 2)
                : round($amountGross, 2);
        }

        $graphDataFinal = [];
        ksort($graphData);
        $currentYear = date('Y', strtotime($startdate));

        foreach ($graphData as $key => $val) {
            if (!isset($graphData[$key]['revenue_gross'])) {
                $graphData[$key]['revenue_gross'] = 0;
            }
            if (!isset($graphData[$key]['revenue_net'])) {
                $graphData[$key]['revenue_net'] = 0;
            }
            $graphData[$key]['nights_booked'] = $totalNightsByMonth[$key] ?? 0;
            $graphData[$key]['date'] = date('Y-m', strtotime($currentYear . '-' . $key));
            $graphDataFinal[] = $graphData[$key];
        }

        $channelData = [];
        foreach ($channelBookings as $key => $value) {
            $percent = ($value['Amount'] > 0 && $totalBookings > 0)
                ? ($value['Amount'] / $totalBookings) * 100
                : 0;
            $channelData[] = [
                'name' => $key,
                'total' => $value['Amount'],
                'percent' => (float) $percent,
            ];
        }

        $respArray = [
            'total_bookings' => $totalBookings,
            'avail_days' => $availDays,
            'occu' => $occu,
            'total_sales_gross' => $totalSalesGross,
            'total_sales_net' => $totalSales,
            'avg_stay' => $avgStay,
            'graph_data' => $graphDataFinal,
            'graph_data_net' => $graphNet,
            'graph_data_gross' => $graphGross,
            'graph_data_nights' => $totalNightsByMonth,
            'channel_data' => $channelData,
            'diff' => $diff,
            'owner_nights' => $ownerNights,
            'block_nights' => $blockNights,
            'maintenance_nights' => $maintenanceNights,
            'photo_shoot_nights' => $photoShootNights,
            'currency' => $currency,
        ];

        return $this->corsJson($respArray, 200);
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
