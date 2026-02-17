<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class BookingsController extends Controller
{
    public function index(Request $request)
    {
        return $this->notImplemented();
    }

    private function getPriceListsTableName(): string
    {
        return 'rahosktnfe_db1.price_lists';
    }

    public function show(Request $request, $id)
    {
        $this->assertApiKey($request);

        $arrivalStart = $request->header('arrivalstart');
        $arrivalEnd = $request->header('arrivalend');
        $departureStart = $request->header('departurestart');
        $departureEnd = $request->header('departureend');
        $startDateHeader = $request->header('startdate');
        $arrivalHeader = $request->header('arrival');
        $departureHeader = $request->header('departure');
        $overlap = strtolower((string) $request->header('overlap', 'false')) === 'true';
        $propIdsHeader = $request->header('propid');
        $showNightsbridge = strtolower((string) $request->header('nightsbridge', 'false')) === 'true';

        if (!$arrivalStart && $arrivalHeader && $departureHeader) {
            $arrivalStart = $arrivalHeader;
            $arrivalEnd = $departureHeader;
        }

        if ($startDateHeader) {
            if (preg_match('/^\d{4}-\d{1,2}$/', $startDateHeader)) {
                $startDate = date('Y-m-d', strtotime($startDateHeader . '-01'));
            } else {
                $startDate = date('Y-m-d', strtotime('01-' . $startDateHeader));
            }
            $endDate = date('Y-m-t', strtotime($startDate));
        } else {
            $startDate = null;
            $endDate = null;
        }

        $user = DB::table('users')->where('id', '=', $id)->first();
        if (!$user) {
            return $this->corsJson(['code' => 404, 'message' => 'User not found'], 404);
        }

        $groupId = DB::table('users_groups')->where('user_id', '=', $id)->value('user_group_id');
        if ($groupId === null) {
            $groupId = 2;
        }
        $propIds = $propIdsHeader ? array_filter(explode(',', $propIdsHeader)) : null;

        $baseQuery = DB::table('virtualdesigns_erpbookings_erpbookings as booking')
            ->join('virtualdesigns_properties_properties as property', 'booking.property_id', '=', 'property.id')
            ->leftJoin('users as manager', 'property.user_id', '=', 'manager.id')
            ->leftJoin('users as salesperson', 'booking.made_by', '=', 'salesperson.id')
            ->whereNull('booking.deleted_at');

        if ((int) $groupId === 1) {
            $baseQuery->where('property.owner_id', '=', $id);
        } elseif ((int) $groupId === 3) {
            $baseQuery->where('property.user_id', '=', $id);
        } elseif ((int) $groupId === 5) {
            $baseQuery->where('property.bodycorp_id', '=', $id);
        }

        if ((int) $id === 1636 || (int) $id === 1709) {
            $baseQuery->where('property.name', 'like', '%Winelands Golf Lodges%');
        }

        if ($arrivalStart && $arrivalEnd) {
            $baseQuery->where('booking.arrival_date', '>=', date('Y-m-d', strtotime($arrivalStart)))
                ->where('booking.arrival_date', '<=', date('Y-m-d', strtotime($arrivalEnd)));
        }

        if ($departureStart && $departureEnd) {
            $baseQuery->where('booking.departure_date', '>=', date('Y-m-d', strtotime($departureStart)))
                ->where('booking.departure_date', '<=', date('Y-m-d', strtotime($departureEnd)));
        }

        if ($startDate && $endDate) {
            $baseQuery->where('booking.arrival_date', '<=', $endDate)
                ->where('booking.departure_date', '>=', $startDate);
        }

        if ($showNightsbridge) {
            $baseQuery->where('booking.status', '=', 0);
        }

        if ($propIds) {
            $baseQuery->whereIn('booking.property_id', $propIds);
        }

        if ($request->header('todayarrival')) {
            $baseQuery->where('booking.arrival_date', '=', date('Y-m-d', strtotime($request->header('todayarrival'))));
        }

        if ($request->header('todaydeparture')) {
            $baseQuery->where('booking.departure_date', '=', date('Y-m-d', strtotime($request->header('todaydeparture'))));
        }

        if ($request->header('istoday')) {
            $today = date('Y-m-d');
            $baseQuery->where('booking.arrival_date', '<', $today)
                ->where('booking.departure_date', '>', $today);
        }

        $bookings = $baseQuery->select(
            'booking.*',
            'property.name as prop_name',
            'property.accounting_name as accounting_name',
            'property.country_id as country_id',
            'manager.name as manager_name',
            'manager.surname as manager_surname',
            'manager.email as manager_email',
            'salesperson.name as salesperson_name',
            'salesperson.surname as salesperson_surname',
            'salesperson.email as salesperson_email',
            'salesperson.id as salesperson_id'
        )->get();

        if ($overlap) {
            $overlapIds = [];
            $count = count($bookings);

            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($bookings[$i]->property_id !== $bookings[$j]->property_id) {
                        continue;
                    }
                    if ($bookings[$i]->arrival_date === $bookings[$j]->departure_date
                        || $bookings[$i]->departure_date === $bookings[$j]->arrival_date
                    ) {
                        continue;
                    }
                    $overlaps = $bookings[$i]->arrival_date < $bookings[$j]->departure_date
                        && $bookings[$i]->departure_date > $bookings[$j]->arrival_date;
                    if ($overlaps) {
                        $overlapIds[$bookings[$i]->id] = true;
                        $overlapIds[$bookings[$j]->id] = true;
                    }
                }
            }

            $bookings = $bookings->filter(function ($booking) use ($overlapIds) {
                return isset($overlapIds[$booking->id]);
            })->values();
        }

        foreach ($bookings as $booking) {
            if (!isset($booking->currency)) {
                if ((int) $booking->country_id === 846) {
                    $booking->currency = 'MUR';
                } elseif ((int) $booking->country_id === 854) {
                    $booking->currency = 'AED';
                } else {
                    $booking->currency = 'ZAR';
                }
            }
        }

        if ($showNightsbridge && $startDate) {
            $nbStartDate = $startDate;
            if (date('m', strtotime($startDate)) === date('m') && strtotime($startDate) < strtotime(date('Y-m-d'))) {
                $nbStartDate = date('Y-m-d');
            }

            $nights = (int) $request->header('nights', 0);
            if ($nights > 1) {
                $nights = $nights - 1;
            }
            $endAvail = $nights > 0
                ? date('Y-m-d', strtotime($nbStartDate . ' + ' . $nights . ' days'))
                : ($endDate ?? $nbStartDate);

            $availabilityData = $this->loadAvailabilityData($nbStartDate, $endAvail, $propIds ?? []);
            if (!empty($availabilityData)) {
                $bookingsByProp = [];
                foreach ($bookings as $booking) {
                    $bookingsByProp[$booking->property_id][] = $booking;
                }
                $availabilityEntries = $this->buildAvailabilityEntries($availabilityData, $nbStartDate, $endAvail, $bookingsByProp);
                if (!empty($availabilityEntries)) {
                    $bookings = $bookings->concat(collect($availabilityEntries));
                }
            }
        }

        return $this->corsJson($bookings, 200);
    }

    private function loadAvailabilityData(string $startDate, string $endDate, array $propIds): array
    {
        $propertiesQuery = DB::table('virtualdesigns_properties_properties')->whereNull('deleted_at');
        if (!empty($propIds)) {
            $propertiesQuery->whereIn('id', $propIds);
        }
        $properties = $propertiesQuery->get();

        if ($properties->isEmpty()) {
            return [];
        }

        $siteSettings = DB::table('virtualdesigns_settings_settings')->first();
        $nbActive = $siteSettings && (int) $siteSettings->nb_active === 1;

        $availData = [];
        foreach ($properties as $index => $prop) {
            $propAvail = null;
            if ($prop->pricelabs_id !== null) {
                try {
                    $priceListsTable = $this->getPriceListsTableName();
                    $propAvail = DB::table($priceListsTable)
                        ->where('pl_id', '=', $prop->pricelabs_id)
                        ->where('date', '>=', $startDate)
                        ->where('date', '<=', $endDate)
                        ->get();
                } catch (\Throwable $e) {
                    $propAvail = null;
                }
            } elseif ($nbActive) {
                $payload = [
                    'bbid' => (int) $prop->nb_id,
                    'startdate' => date('Y-m-d', strtotime($startDate)),
                    'enddate' => date('Y-m-d', strtotime($endDate)),
                    'showrates' => true,
                    'strictsearch' => false,
                ];
                $response = $this->nightsbridgePost('https://www.nightsbridge.co.za/bridge/api/5.0/availgrid', $payload);
                if ($response && isset($response->success) && $response->success === true) {
                    if ((int) $prop->as_room === 1 && $prop->bbrtid && isset($response->data->roomtypes)) {
                        foreach ($response->data->roomtypes as $roomType) {
                            if (isset($roomType->rtid) && (string) $roomType->rtid === (string) $prop->bbrtid) {
                                $propAvail = $roomType->availability ?? null;
                                break;
                            }
                        }
                    } else {
                        $propAvail = $response->data->roomtypes ?? null;
                    }
                }
            }

            $availData[$index]['propid'] = $prop->id;
            $availData[$index]['avail'] = $propAvail;
        }

        foreach ($availData as $idx => $data) {
            if (isset($data['avail']) && is_array($data['avail'])) {
                $dateCursor = date('Y-m-d', strtotime($startDate));
                foreach ($data['avail'] as $avItem) {
                    if (is_object($avItem) && !isset($avItem->date)) {
                        $avItem->date = $dateCursor;
                    } elseif (is_array($avItem) && !isset($avItem['date'])) {
                        $avItem['date'] = $dateCursor;
                    }
                    $dateCursor = date('Y-m-d', strtotime($dateCursor . ' + 1 days'));
                }
            }
            $availData[$idx] = $data;
        }

        return $availData;
    }

    private function buildAvailabilityEntries(array $availabilityData, string $startDate, string $endDate, array $bookingsByProp): array
    {
        $entries = [];
        $count = 0;

        foreach ($availabilityData as $data) {
            $propId = $data['propid'] ?? null;
            $avail = $data['avail'] ?? null;
            if ($propId === null || $avail === null || is_string($avail) || empty($avail)) {
                continue;
            }

            $availStart = null;
            $availCount = 0;
            $dateCursor = date('Y-m-d', strtotime($startDate));

            foreach ($avail as $dayAvail) {
                $dayDate = is_object($dayAvail)
                    ? ($dayAvail->date ?? $dateCursor)
                    : ($dayAvail['date'] ?? $dateCursor);
                $dateCursor = date('Y-m-d', strtotime($dayDate . ' + 1 days'));

                $hasBooking = false;
                if (isset($bookingsByProp[$propId])) {
                    foreach ($bookingsByProp[$propId] as $booking) {
                        if ($booking->arrival_date < $dayDate && $booking->departure_date > $dayDate) {
                            $hasBooking = true;
                            break;
                        }
                    }
                }
                if ($hasBooking) {
                    continue;
                }

                $isAvailable = true;
                if (is_object($dayAvail) && isset($dayAvail->booked)) {
                    $isAvailable = (int) $dayAvail->booked === 0;
                } elseif (is_array($dayAvail) && isset($dayAvail['booked'])) {
                    $isAvailable = (int) $dayAvail['booked'] === 0;
                } elseif (is_object($dayAvail) && isset($dayAvail->noroomsfree)) {
                    $isAvailable = (int) $dayAvail->noroomsfree > 0;
                } elseif (is_array($dayAvail) && isset($dayAvail['noroomsfree'])) {
                    $isAvailable = (int) $dayAvail['noroomsfree'] > 0;
                } elseif (is_object($dayAvail) && isset($dayAvail->available)) {
                    $isAvailable = (bool) $dayAvail->available;
                } elseif (is_array($dayAvail) && isset($dayAvail['available'])) {
                    $isAvailable = (bool) $dayAvail['available'];
                }

                if ($isAvailable) {
                    $price = is_object($dayAvail)
                        ? ($dayAvail->price ?? $dayAvail->roomrate ?? null)
                        : ($dayAvail['price'] ?? $dayAvail['roomrate'] ?? null);
                    $currency = is_object($dayAvail)
                        ? ($dayAvail->currency ?? null)
                        : ($dayAvail['currency'] ?? null);

                    if ($price !== null) {
                        $entries[] = [
                            'id' => 'a' . $count,
                            'arrival_date' => $dayDate,
                            'departure_date' => date('Y-m-d', strtotime($dayDate . ' + 1 days')),
                            'property_id' => $propId,
                            'client_name' => '',
                            'prop_name' => '',
                            'booking_ref' => '',
                            'channel' => 'rate',
                            'status' => 0,
                            'price' => $price,
                            'currency' => $currency,
                            'created_at' => $dayDate,
                        ];
                        $count++;
                    }

                    if ($availStart !== null) {
                        $depDate = date('Y-m-d', strtotime($availStart . ' + ' . max(1, $availCount + 1) . ' days'));
                        $entries[] = [
                            'id' => 'a' . $count,
                            'arrival_date' => $availStart,
                            'departure_date' => $depDate,
                            'property_id' => $propId,
                            'client_name' => '',
                            'prop_name' => '',
                            'booking_ref' => '',
                            'channel' => 'availability',
                            'status' => 0,
                            'price' => '',
                            'created_at' => $availStart,
                        ];
                        $count++;
                        $availStart = null;
                        $availCount = 0;
                    }
                } else {
                    if ($availStart === null) {
                        $availStart = $dayDate;
                        $availCount = 0;
                    } else {
                        $availCount++;
                    }

                    if ($dayDate === $endDate) {
                        $depDate = date('Y-m-d', strtotime($availStart . ' + ' . max(1, $availCount + 1) . ' days'));
                        $entries[] = [
                            'id' => 'a' . $count,
                            'arrival_date' => $availStart,
                            'departure_date' => $depDate,
                            'property_id' => $propId,
                            'client_name' => '',
                            'prop_name' => '',
                            'booking_ref' => '',
                            'channel' => 'availability',
                            'status' => 0,
                            'price' => '',
                            'created_at' => $availStart,
                        ];
                        $count++;
                        $availStart = null;
                        $availCount = 0;
                    }
                }
            }
        }

        return $entries;
    }

    public function store(Request $request)
    {
        return $this->notImplemented();
    }

    public function update(Request $request, $id)
    {
        return $this->notImplemented();
    }

    public function destroy($id)
    {
        return $this->notImplemented();
    }

    public function checkAvail(Request $request)
    {
        $this->assertApiKey($request);

        $arrival = $request->header('arrival');
        $departure = $request->header('departure');
        $propId = $request->header('propid');
        $bookingType = $request->header('booking-type');
        $minstayOverride = strtolower((string) $request->header('minstay-override', 'false')) === 'true';

        if ($arrival === null || $departure === null || $propId === null) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing required headers'], 400);
        }

        // Defensive: if arrival/departure are inverted, swap them so queries use a valid range
        try {
            if (strtotime($arrival) > strtotime($departure)) {
                [$arrival, $departure] = [$departure, $arrival];
            }
        } catch (\Throwable $e) {
            // ignore and proceed; validation will catch invalid dates later
        }

        $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $propId)->first();
        if (!$prop) {
            return $this->corsJson(['code' => 404, 'message' => 'Property not found'], 404);
        }

            if ($prop->pricelabs_id !== null) {
            $opInfo = DB::table('virtualdesigns_operationalinformation_operationalinformation')
                ->where('property_id', '=', $propId)
                ->first();
            $managerFees = DB::table('virtualdesigns_propertymanagerfees_propertymanagerfees')
                ->where('property_id', '=', $propId)
                ->first();
            $extras = DB::table('virtualdesigns_extracharges_extracharges')
                ->where('property_id', '=', $propId)
                ->first();

            try {
                $priceListsTable = $this->getPriceListsTableName();
                $availRows = DB::table($priceListsTable)
                    ->where('pl_id', '=', $prop->pricelabs_id)
                    ->where('date', '>=', $arrival)
                    ->where('date', '<=', $departure)
                    ->get();
            } catch (\Throwable $e) {
                $availRows = collect([]);
            }

            $nights = 0;
            $total = 0.0;
            $isAvail = true;
            $currency = 'ZAR';
            $minStay = 0;

            foreach ($availRows as $row) {
                if ($row->date < $departure) {
                    $nights++;
                    $total += (float) $row->price;
                    $currency = $row->currency;
                    if ((int) $row->booked === 1) {
                        $isAvail = false;
                    }
                    if ($row->date < date('Y-m-d')) {
                        $isAvail = false;
                    }
                    $minStay = (int) $row->min_stay;
                }
            }

            if ($nights < $minStay && !$minstayOverride) {
                return $this->corsJson([
                    'code' => 412,
                    'message' => 'This property has a minimum stay of ' . $minStay . ' nights, please increase your date range',
                ], 412);
            }

            $tasks = [];
            if ($managerFees !== null) {
                if ($this->isHoliday($arrival)) {
                    $arrivalCleanPrice = (float) $managerFees->arrival_clean * 1.5;
                    $arrivalConciergePrice = (float) $managerFees->concierge_fee_arrival;
                } else {
                    $arrivalCleanPrice = (float) $managerFees->arrival_clean;
                    $arrivalConciergePrice = (float) $managerFees->concierge_fee_arrival;
                }
            } else {
                $arrivalCleanPrice = 0.0;
                $arrivalConciergePrice = 0.0;
            }

            $tasks[] = [
                'task_type' => 'Arrival Clean',
                'task_date' => $arrival,
                'task_price' => $arrivalCleanPrice,
                'task_user' => $prop->user_id,
            ];
            $tasks[] = [
                'task_type' => 'Concierge Arrival',
                'task_date' => $arrival,
                'task_price' => $arrivalConciergePrice,
                'task_user' => $prop->user_id,
            ];
            $tasks[] = [
                'task_type' => 'Welcome Pack',
                'task_date' => $arrival,
                'task_price' => $managerFees ? (float) $managerFees->welcome_pack : 0.0,
                'task_user' => $prop->user_id,
            ];
            if ($opInfo !== null && (int) $opInfo->linen_pool === 1 && (int) $opInfo->departure_linen === 0) {
                $tasks[] = [
                    'task_type' => 'Arrival Laundry',
                    'task_date' => $arrival,
                    'task_price' => $extras ? (float) $extras->fanote_prices : 0.0,
                    'task_user' => $opInfo->linen_supplier_id,
                ];
            }

            if ($managerFees !== null) {
                if ($this->isHoliday($departure)) {
                    $departureCleanPrice = (float) $managerFees->departure_clean * 1.5;
                    $departureConciergePrice = (float) $managerFees->concierge_fee_departure * 1.5;
                } else {
                    $departureCleanPrice = (float) $managerFees->departure_clean;
                    $departureConciergePrice = (float) $managerFees->concierge_fee_departure;
                }
            } else {
                $departureCleanPrice = 0.0;
                $departureConciergePrice = 0.0;
            }

            $tasks[] = [
                'task_type' => 'Departure Clean',
                'task_date' => $departure,
                'task_price' => $departureCleanPrice,
                'task_user' => $prop->user_id,
            ];
            $tasks[] = [
                'task_type' => 'Concierge Departure',
                'task_date' => $departure,
                'task_price' => $departureConciergePrice,
                'task_user' => $prop->user_id,
            ];
            if ($opInfo !== null && (int) $opInfo->linen_pool === 1 && (int) $opInfo->departure_linen === 1) {
                $tasks[] = [
                    'task_type' => 'Departure Laundry',
                    'task_date' => $departure,
                    'task_price' => $extras ? (float) $extras->fanote_prices : 0.0,
                    'task_user' => $opInfo->linen_supplier_id,
                ];
            }

            $msaCount = 1;
            $msaDate = $arrival;
            $hasFirst = false;
            while (strtotime($msaDate) < strtotime($departure)) {
                $msaDate = date('Y-m-d', strtotime($msaDate . '+ 1 day'));
                if ($msaCount === 8) {
                    if ($hasFirst) {
                        $taskDate = date('Y-m-d', strtotime($msaDate . '- 9 day'));
                    } else {
                        $taskDate = date('Y-m-d', strtotime($msaDate . '- 5 day'));
                        $hasFirst = true;
                    }
                    $tasks[] = [
                        'task_type' => 'MSA',
                        'task_date' => $taskDate,
                        'task_price' => $managerFees ? (float) $managerFees->mid_stay_clean : 0.0,
                        'task_user' => $prop->user_id,
                    ];
                    if ($opInfo !== null && (int) $opInfo->linen_pool === 1) {
                        $tasks[] = [
                            'task_type' => 'MSA Laundry',
                            'task_date' => $taskDate,
                            'task_price' => $extras ? (float) $extras->fanote_prices : 0.0,
                            'task_user' => $opInfo->linen_supplier_id,
                        ];
                    }
                    $msaCount = 1;
                } else {
                    $msaCount++;
                }
            }

            usort($tasks, function ($a, $b) {
                return strtotime($a['task_date']) <=> strtotime($b['task_date']);
            });

            $startTs = strtotime($arrival);
            $endTs = strtotime($departure);
            $nights = round(($endTs - $startTs) / 86400);
            $dateThirty = date('Y-m-d', strtotime(date('Y-m-d') . ' + 30 days'));

            $bookingFee = (float) $prop->booking_fee;
            $cleanFee = (float) $prop->clean_fee;

            if ((int) $prop->country_id === 846) {
                    $priceListsTable = $this->getPriceListsTableName();
                    $currency = DB::table($priceListsTable)
                        ->where('pl_id', '=', $prop->pricelabs_id)
                        ->value('currency');
                if ($currency === 'EUR') {
                    $conversion = (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'EUR/MUR')->value('rate');
                    $convertedPrice = $total * $conversion;
                    $bookingFee = $bookingFee * $conversion;
                    $cleanFee = $cleanFee * $conversion;
                } elseif ($currency === 'USD') {
                    $conversion = (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'USD/MUR')->value('rate');
                    $convertedPrice = $total * $conversion;
                } elseif ($currency === 'ZAR') {
                    $usdRate = (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'ZAR/USD')->value('rate');
                    $murRate = (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'USD/MUR')->value('rate');
                    $convertedPrice = ($total * $usdRate) * $murRate;
                    $bookingFee = $bookingFee * $usdRate;
                    $cleanFee = $cleanFee * $usdRate;
                } else {
                    $convertedPrice = $total;
                }

                $depositAmount = $convertedPrice + $bookingFee + $cleanFee;
                if ($arrival >= $dateThirty) {
                    $depositAmount = $depositAmount / 2;
                }

                return $this->corsJson([
                    'accounting_name' => $prop->accounting_name,
                    'available' => $isAvail,
                    'capacity' => $prop->capacity,
                    'allow_child' => true,
                    'child_min' => 0,
                    'child_max' => 18,
                    'nights' => $nights,
                    'price_per_night' => $nights > 0 ? $convertedPrice / $nights : 0,
                    'booking_fee' => $bookingFee,
                    'clean_fee' => $cleanFee,
                    'damage_deposit' => $prop->damage_deposit,
                    'total' => $convertedPrice + (float) $prop->booking_fee + (float) $prop->clean_fee,
                    'tasks' => $tasks,
                    'deposit_amount' => $depositAmount,
                    'currency' => 'MUR',
                ], 200);
            }

            if ((int) $prop->country_id === 854) {
                $tax = ($prop->bedroom_num * 10) * min($nights, 30);
                $depositAmount = $total + (float) $prop->booking_fee + (float) $prop->clean_fee + $tax;
                if ($arrival >= $dateThirty) {
                    $depositAmount = $depositAmount / 2;
                }

                return $this->corsJson([
                    'accounting_name' => $prop->accounting_name,
                    'available' => $isAvail,
                    'capacity' => $prop->capacity,
                    'allow_child' => true,
                    'child_min' => 0,
                    'child_max' => 18,
                    'nights' => $nights,
                    'price_per_night' => $nights > 0 ? $total / $nights : 0,
                    'booking_fee' => $prop->booking_fee,
                    'clean_fee' => $prop->clean_fee,
                    'damage_deposit' => $prop->damage_deposit,
                    'total' => $total + (float) $prop->booking_fee + (float) $prop->clean_fee,
                    'tasks' => $tasks,
                    'deposit_amount' => $depositAmount,
                    'tax' => $tax,
                    'currency' => $currency,
                ], 200);
            }

            $depositAmount = $total + (float) $prop->booking_fee + (float) $prop->clean_fee;
            if ($arrival >= $dateThirty) {
                $depositAmount = $depositAmount / 2;
            }

            if ($currency !== 'ZAR') {
                $conversion = (float) DB::table('virtualdesigns_exchange_rates')
                    ->where('symbol', '=', 'ZAR/' . $currency)
                    ->value('rate');
                $depositAmount = $total + ($prop->booking_fee * $conversion) + ($prop->clean_fee * $conversion);
                if ($arrival >= $dateThirty) {
                    $depositAmount = $depositAmount / 2;
                }

                return $this->corsJson([
                    'available' => $isAvail,
                    'capacity' => $prop->capacity,
                    'allow_child' => true,
                    'child_min' => 0,
                    'child_max' => 18,
                    'nights' => $nights,
                    'price_per_night' => $nights > 0 ? $total / $nights : 0,
                    'booking_fee' => $prop->booking_fee * $conversion,
                    'clean_fee' => $prop->clean_fee * $conversion,
                    'damage_deposit' => $prop->damage_deposit * $conversion,
                    'total' => $total + ($prop->booking_fee * $conversion) + ($prop->clean_fee * $conversion),
                    'tasks' => $tasks,
                    'deposit_amount' => $depositAmount,
                    'currency' => $currency,
                ], 200);
            }

            return $this->corsJson([
                'accounting_name' => $prop->accounting_name,
                'available' => $isAvail,
                'capacity' => $prop->capacity,
                'allow_child' => true,
                'child_min' => 0,
                'child_max' => 18,
                'nights' => $nights,
                'price_per_night' => $nights > 0 ? $total / $nights : 0,
                'booking_fee' => $prop->booking_fee,
                'clean_fee' => $prop->clean_fee,
                'damage_deposit' => $prop->damage_deposit,
                'total' => $total + (float) $prop->booking_fee + (float) $prop->clean_fee,
                'tasks' => $tasks,
                'deposit_amount' => $depositAmount,
                'currency' => $currency,
            ], 200);
        }

        $siteSettings = DB::table('virtualdesigns_settings_settings')->first();
        if (!$siteSettings || (int) $siteSettings->nb_active !== 1) {
            return $this->corsJson(['code' => 404, 'message' => 'No Availability Received'], 404);
        }

        if ((int) $prop->as_room === 1) {
            $payload = [
                'messagename' => 'AvailRQ',
                'bbid' => (int) $prop->nb_id,
                'bbrtid' => $prop->bbrtid,
                'startdate' => date('Y-m-d', strtotime($arrival)),
                'enddate' => date('Y-m-d', strtotime($departure)),
                'showrates' => true,
                'showroomcount' => true,
                'showextra' => true,
                'nightsbridge' => true,
                'strictsearch' => false,
            ];
        } else {
            $payload = [
                'messagename' => 'AvailRQ',
                'bbid' => (int) $prop->nb_id,
                'startdate' => date('Y-m-d', strtotime($arrival)),
                'enddate' => date('Y-m-d', strtotime($departure)),
                'showrates' => true,
                'showroomcount' => true,
                'showextra' => true,
                'nightsbridge' => true,
                'strictsearch' => false,
            ];
        }

        $response = $this->nightsbridgePost('https://www.nightsbridge.co.za/bridge/api/5.0/availability', $payload);
        if (!$response || !isset($response->success) || $response->success !== 'true') {
            $errorMessage = isset($response->error) ? json_encode($response->error) : 'No Availability Received';
            return $this->corsJson(['code' => 400, 'message' => $errorMessage], 400);
        }

        if (isset($response->data->bb[0]->noavailability) && $response->data->bb[0]->noavailability->status === 'F') {
            return $this->corsJson(['code' => 404, 'message' => $response->data->bb[0]->noavailability->description], 404);
        }

        if (!isset($response->data->bb[0]->roomtypes[0])) {
            return $this->corsJson(['code' => 404, 'message' => 'No Availability Received'], 404);
        }

        $propData = $response->data->bb[0]->roomtypes[0];
        $allowChild = $propData->childpolicy->allowchild1 == true || $propData->childpolicy->allowchild2 == true;
        $childMin = $response->data->bb[0]->childpolicy->lowerlimit;
        if ($response->data->bb[0]->childpolicy->childage2 > 0 && $propData->childpolicy->allowchild2 == true) {
            $childMax = $response->data->bb[0]->childpolicy->childage2;
        } else {
            $childMax = $propData->childpolicy->childage1;
        }

        $startTs = strtotime($arrival);
        $endTs = strtotime($departure);
        $nights = round(($endTs - $startTs) / 86400);

        $skipMinStay = in_array($bookingType, ['owner_booking', 'owner_guest_booking', 'maintenance', 'photo_shoot', 'deep_clean', 'block'], true);
        if (!$skipMinStay && $nights < $propData->minlos && !$minstayOverride) {
            return $this->corsJson([
                'code' => 412,
                'message' => 'This property has a minimum stay of ' . $propData->minlos . ' nights, please increase your date range',
            ], 412);
        }

        return $this->corsJson([
            'accounting_name' => $prop->accounting_name,
            'available' => true,
            'capacity' => $propData->maxoccupancy,
            'allow_child' => $allowChild,
            'child_min' => $childMin,
            'child_max' => $childMax,
        ], 200);
    }

    public function nightsbridgeBookings(Request $request)
    {
        $this->assertApiKey($request);

        $propId = $request->header('propid');
        $startDate = $request->header('startdate');
        $endDate = $request->header('enddate');

        if ($propId === null || $startDate === null || $endDate === null) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing required headers'], 400);
        }

        $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $propId)->first();
        if (!$prop) {
            return $this->corsJson(['code' => 404, 'message' => 'Property not found'], 404);
        }

        $siteSettings = DB::table('virtualdesigns_settings_settings')->first();
        if (!$siteSettings || (int) $siteSettings->nb_active !== 1) {
            return $this->corsJson('Nightsbridge Disabled', 400);
        }

        $payload = [
            'bbid' => (int) $prop->nb_id,
            'startdate' => date('Y-m-d', strtotime($startDate)),
            'enddate' => date('Y-m-d', strtotime($endDate)),
            'showrates' => true,
            'strictsearch' => false,
        ];

        $response = $this->nightsbridgePost('https://www.nightsbridge.co.za/bridge/api/5.0/availgrid', $payload);
        if (!$response || !isset($response->success) || $response->success === false) {
            $errorMessage = isset($response->error->message) ? $response->error->message : 'No data received from Nightsbridge';
            return $this->corsJson($errorMessage, 400);
        }

        $availData = null;
        $roomData = null;
        if (isset($response->data)) {
            if ((int) $prop->as_room === 1 && $prop->bbrtid) {
                $roomTypes = $response->data->roomtypes ?? [];
                foreach ($roomTypes as $roomType) {
                    if (isset($roomType->rtid) && (string) $roomType->rtid === (string) $prop->bbrtid) {
                        $roomData = $roomType;
                        break;
                    }
                }
                if ($roomData && isset($roomData->availability)) {
                    $availData = $roomData->availability;
                }
            } else {
                $availData = $response->data->roomtypes ?? null;
            }
        }

        if ($availData === null) {
            return $this->corsJson('No data received from Nightsbridge', 404);
        }

        if (is_array($availData)) {
            $dateCursor = date('Y-m-d', strtotime($startDate));
            foreach ($availData as $item) {
                if (is_object($item)) {
                    $item->date = $dateCursor;
                    $item->capacity = $roomData->maxoccupancy ?? $prop->capacity ?? null;
                }
                $dateCursor = date('Y-m-d', strtotime($dateCursor . ' + 1 days'));
            }
        }

        return $this->corsJson($availData, 200);
    }

    public function nightsbridgeBookingsAll(Request $request)
    {
        $this->assertApiKey($request);

        $startDate = $request->header('startdate');
        $endDate = $request->header('enddate');

        if ($startDate === null || $endDate === null) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing required headers'], 400);
        }

        $siteSettings = DB::table('virtualdesigns_settings_settings')->first();
        if (!$siteSettings || (int) $siteSettings->nb_active !== 1) {
            return $this->corsJson('Nightsbridge Disabled', 400);
        }

        $properties = DB::table('virtualdesigns_properties_properties')
            ->where('is_live', '=', 1)
            ->whereNull('deleted_at')
            ->get();

        $availData = [];
        foreach ($properties as $index => $prop) {
            $payload = [
                'bbid' => (int) $prop->nb_id,
                'startdate' => date('Y-m-d', strtotime($startDate)),
                'enddate' => date('Y-m-d', strtotime($endDate)),
                'showrates' => true,
                'strictsearch' => false,
            ];

            $response = $this->nightsbridgePost('https://www.nightsbridge.co.za/bridge/api/5.0/availgrid', $payload);
            if (!$response || !isset($response->success) || $response->success === false) {
                $availData[$index]['propid'] = $prop->id;
                $availData[$index]['avail'] = isset($response->error->message) ? $response->error->message : 'No data received from Nightsbridge';
                continue;
            }

            $propAvail = null;
            if (isset($response->data)) {
                if ((int) $prop->as_room === 1 && $prop->bbrtid) {
                    $roomTypes = $response->data->roomtypes ?? [];
                    foreach ($roomTypes as $roomType) {
                        if (isset($roomType->rtid) && (string) $roomType->rtid === (string) $prop->bbrtid) {
                            $propAvail = $roomType->availability ?? null;
                            break;
                        }
                    }
                } else {
                    $propAvail = $response->data->roomtypes ?? null;
                }
            }

            $availData[$index]['propid'] = $prop->id;
            $availData[$index]['avail'] = $propAvail ?? 'No data received from Nightsbridge';
        }

        if (!empty($availData)) {
            foreach ($availData as $idx => $data) {
                $dateCursor = date('Y-m-d', strtotime($startDate));
                if (isset($data['avail']) && is_array($data['avail'])) {
                    foreach ($data['avail'] as $avItem) {
                        if (is_object($avItem)) {
                            $avItem->date = $dateCursor;
                        } elseif (is_array($avItem)) {
                            $avItem['date'] = $dateCursor;
                        }
                        $dateCursor = date('Y-m-d', strtotime($dateCursor . ' + 1 days'));
                    }
                }
                $availData[$idx] = $data;
            }
            return $this->corsJson($availData, 200);
        }

        return $this->corsJson('No data received from Nightsbridge', 404);
    }

    public function nightsbridgeBookingsList(Request $request)
    {
        $this->assertApiKey($request);

        $startDate = $request->header('startdate') ?? $request->input('startdate');
        $endDate = $request->header('enddate') ?? $request->input('enddate');
        $propIdsHeader = $request->header('propids') ?? $request->input('propids');

        if ($startDate === null || $endDate === null) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing required dates'], 400);
        }

        if (strtotime($startDate) < strtotime(date('Y-m-d'))) {
            $startDate = date('Y-m-d');
        }

        $propIds = [];
        if ($propIdsHeader !== null) {
            $propIds = array_filter(array_map('trim', explode(',', (string) $propIdsHeader)));
        }

        $propertiesQuery = DB::table('virtualdesigns_properties_properties')->whereNull('deleted_at');
        if (!empty($propIds)) {
            $propertiesQuery->whereIn('id', $propIds);
        }
        $properties = $propertiesQuery->get();

        $siteSettings = DB::table('virtualdesigns_settings_settings')->first();
        $nbActive = $siteSettings && (int) $siteSettings->nb_active === 1;

        $availData = [];
        foreach ($properties as $index => $prop) {
            $propAvail = null;
            if ($prop->pricelabs_id !== null) {
                try {
                    $priceListsTable = $this->getPriceListsTableName();
                    $propAvail = DB::table($priceListsTable)
                        ->where('pl_id', '=', $prop->pricelabs_id)
                        ->where('date', '>=', $startDate)
                        ->where('date', '<=', $endDate)
                        ->get();
                } catch (\Throwable $e) {
                    $propAvail = collect([]);
                }
            } elseif ($nbActive) {
                $payload = [
                    'bbid' => (int) $prop->nb_id,
                    'startdate' => date('Y-m-d', strtotime($startDate)),
                    'enddate' => date('Y-m-d', strtotime($endDate)),
                    'showrates' => true,
                    'strictsearch' => false,
                ];
                $response = $this->nightsbridgePost('https://www.nightsbridge.co.za/bridge/api/5.0/availgrid', $payload);
                if ($response && isset($response->success) && $response->success === true) {
                    if ((int) $prop->as_room === 1 && $prop->bbrtid && isset($response->data->roomtypes)) {
                        foreach ($response->data->roomtypes as $roomType) {
                            if (isset($roomType->rtid) && (string) $roomType->rtid === (string) $prop->bbrtid) {
                                $propAvail = $roomType->availability ?? null;
                                break;
                            }
                        }
                    } else {
                        $propAvail = $response->data->roomtypes ?? null;
                    }
                }
            }

            $availData[$index]['propid'] = $prop->id;
            $availData[$index]['avail'] = $propAvail;
        }

        foreach ($availData as $idx => $data) {
            if (isset($data['avail']) && is_array($data['avail'])) {
                $dateCursor = date('Y-m-d', strtotime($startDate));
                foreach ($data['avail'] as $avItem) {
                    if (is_object($avItem)) {
                        $avItem->date = $dateCursor;
                    } elseif (is_array($avItem)) {
                        $avItem['date'] = $dateCursor;
                    }
                    $dateCursor = date('Y-m-d', strtotime($dateCursor . ' + 1 days'));
                }
            }
            $availData[$idx] = $data;
        }

        return $this->corsJson($availData, 200);
    }

    public function ownerBooking(Request $request)
    {
        $payload = $request->all();
        if (!isset($payload['booking_type'])) {
            $payload['booking_type'] = 'owner_booking';
        }
        $request->replace($payload);

        return $this->guestBooking($request);
    }

    public function allocateBooking(Request $request)
    {
        $this->assertApiKey($request);

        $body = (object) $request->all();
        if (!isset($body->propid, $body->arrival, $body->departure)) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing required fields'], 400);
        }

        $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $body->propid)->first();
        if (!$prop) {
            return $this->corsJson(['code' => 404, 'message' => 'Property not found'], 404);
        }

        $opInfo = DB::table('virtualdesigns_operationalinformation_operationalinformation')
            ->where('property_id', '=', $body->propid)
            ->first();
        $managerFees = DB::table('virtualdesigns_propertymanagerfees_propertymanagerfees')
            ->where('property_id', '=', $body->propid)
            ->first();
        $extras = DB::table('virtualdesigns_extracharges_extracharges')
            ->where('property_id', '=', $body->propid)
            ->first();

        $siteSettings = DB::table('virtualdesigns_settings_settings')->first();
        if (!$siteSettings || (int) $siteSettings->nb_active !== 1) {
            return $this->corsJson('Nightsbridge Disabled', 400);
        }

        if ((int) $prop->as_room === 1) {
            $payload = [
                'messagename' => 'AvailRQ',
                'bbid' => (int) $prop->nb_id,
                'bbrtid' => $prop->bbrtid,
                'startdate' => date('Y-m-d', strtotime($body->arrival)),
                'enddate' => date('Y-m-d', strtotime($body->departure)),
                'showrates' => true,
                'strictsearch' => false,
                'nightsbridge' => true,
            ];
        } else {
            $payload = [
                'messagename' => 'AvailRQ',
                'bbid' => (int) $prop->nb_id,
                'startdate' => date('Y-m-d', strtotime($body->arrival)),
                'enddate' => date('Y-m-d', strtotime($body->departure)),
                'showrates' => true,
                'strictsearch' => false,
                'nightsbridge' => true,
            ];
        }

        $availability = $this->nightsbridgePost('https://www.nightsbridge.co.za/bridge/api/5.0/availability', $payload);
        if (!isset($availability->data->bb[0]->roomtypes[0]->mealplans[0]->rateid)) {
            return $this->corsJson(['code' => 404, 'message' => 'Selected dates are not available'], 404);
        }

        $nights = $availability->data->nights;
        $rateId = $availability->data->bb[0]->roomtypes[0]->mealplans[0]->rateid;
        $roomTypes = new \stdClass();
        $roomTypes->rateid = $rateId;

        if (isset($body->booking_type) && in_array($body->booking_type, ['owner_booking', 'owner_guest_booking'], true)) {
            $people = (int) $body->adults + (int) $body->children;
            $roomTypes->adults = $people;
        } elseif (isset($body->booking_type) && $body->booking_type === 'booking') {
            $roomTypes->adults = (int) $body->adults;
            if (!empty($body->children) && isset($body->child_ages)) {
                foreach ($body->child_ages as $childAge) {
                    if ($availability->data->bb[0]->roomtypes[0]->childpolicy->allowchild1 == true
                        && $availability->data->bb[0]->roomtypes[0]->childpolicy->allowchild2 == true) {
                        if ($childAge <= $availability->data->bb[0]->childpolicy->childage1) {
                            $roomTypes->child1 = ($roomTypes->child1 ?? 0) + 1;
                        } else {
                            if ($childAge <= $availability->data->bb[0]->childpolicy->childage2) {
                                $roomTypes->child2 = ($roomTypes->child2 ?? 0) + 1;
                            }
                        }
                    } else {
                        if ($availability->data->bb[0]->roomtypes[0]->childpolicy->allowchild1 == true
                            && $childAge <= $availability->data->bb[0]->childpolicy->childage1) {
                            $roomTypes->child1 = ($roomTypes->child1 ?? 0) + 1;
                        }
                        if ($availability->data->bb[0]->roomtypes[0]->childpolicy->allowchild2 == true
                            && $childAge <= $availability->data->bb[0]->childpolicy->childage2) {
                            $roomTypes->child2 = ($roomTypes->child2 ?? 0) + 1;
                        }
                    }
                }
            }
        } else {
            $roomTypes->adults = $availability->data->bb[0]->roomtypes[0]->maxoccupancy;
        }

        $allocPayload = [
            'bbid' => $prop->nb_id,
            'startdate' => date('Y-m-d', strtotime($body->arrival)),
            'nights' => $nights,
            'roomtypes' => [$roomTypes],
        ];
        $allocResponse = $this->nightsbridgePost('https://www.nightsbridge.co.za/bridge/api/5.0/allocate', $allocPayload);

        if (!isset($allocResponse->success) || $allocResponse->success !== 'true') {
            $errorMessage = isset($allocResponse->error) ? json_encode($allocResponse->error) : 'Allocation failed';
            return $this->corsJson($errorMessage, 400);
        }

        $tasks = [];
        if ($managerFees !== null) {
            if ($this->isHoliday($body->arrival)) {
                $arrivalCleanPrice = (float) $managerFees->arrival_clean * 1.5;
                $arrivalConciergePrice = (float) $managerFees->concierge_fee_arrival;
            } else {
                $arrivalCleanPrice = (float) $managerFees->arrival_clean;
                $arrivalConciergePrice = (float) $managerFees->concierge_fee_arrival;
            }
        } else {
            $arrivalCleanPrice = 0.0;
            $arrivalConciergePrice = 0.0;
        }

        $tasks[] = [
            'task_type' => 'Arrival Clean',
            'task_date' => $body->arrival,
            'task_price' => $arrivalCleanPrice,
            'task_user' => $prop->user_id,
        ];
        $tasks[] = [
            'task_type' => 'Concierge Arrival',
            'task_date' => $body->arrival,
            'task_price' => $arrivalConciergePrice,
            'task_user' => $prop->user_id,
        ];
        $tasks[] = [
            'task_type' => 'Welcome Pack',
            'task_date' => $body->arrival,
            'task_price' => $managerFees ? (float) $managerFees->welcome_pack : 0.0,
            'task_user' => $prop->user_id,
        ];
        if ($opInfo !== null && (int) $opInfo->linen_pool === 1 && (int) $opInfo->departure_linen === 0) {
            $tasks[] = [
                'task_type' => 'Arrival Laundry',
                'task_date' => $body->arrival,
                'task_price' => $extras ? (float) $extras->fanote_prices : 0.0,
                'task_user' => $opInfo->linen_supplier_id,
            ];
        }

        if ($managerFees !== null) {
            if ($this->isHoliday($body->departure)) {
                $departureCleanPrice = (float) $managerFees->departure_clean * 1.5;
                $departureConciergePrice = (float) $managerFees->concierge_fee_departure * 1.5;
            } else {
                $departureCleanPrice = (float) $managerFees->departure_clean;
                $departureConciergePrice = (float) $managerFees->concierge_fee_departure;
            }
        } else {
            $departureCleanPrice = 0.0;
            $departureConciergePrice = 0.0;
        }

        $tasks[] = [
            'task_type' => 'Departure Clean',
            'task_date' => $body->departure,
            'task_price' => $departureCleanPrice,
            'task_user' => $prop->user_id,
        ];
        $tasks[] = [
            'task_type' => 'Concierge Departure',
            'task_date' => $body->departure,
            'task_price' => $departureConciergePrice,
            'task_user' => $prop->user_id,
        ];
        if ($opInfo !== null && (int) $opInfo->linen_pool === 1 && (int) $opInfo->departure_linen === 1) {
            $tasks[] = [
                'task_type' => 'Departure Laundry',
                'task_date' => $body->departure,
                'task_price' => $extras ? (float) $extras->fanote_prices : 0.0,
                'task_user' => $opInfo->linen_supplier_id,
            ];
        }

        $msaCount = 1;
        $msaDate = $body->arrival;
        $hasFirst = false;
        while (strtotime($msaDate) < strtotime($body->departure)) {
            $msaDate = date('Y-m-d', strtotime($msaDate . '+ 1 day'));
            if ($msaCount === 8) {
                if ($hasFirst) {
                    $msaDate = date('Y-m-d', strtotime($msaDate . '- 4 day'));
                } else {
                    $msaDate = date('Y-m-d', strtotime($msaDate . '- 5 day'));
                    $hasFirst = true;
                }
                $tasks[] = [
                    'task_type' => 'MSA',
                    'task_date' => $msaDate,
                    'task_price' => $managerFees ? (float) $managerFees->mid_stay_clean : 0.0,
                    'task_user' => $prop->user_id,
                ];
                if ($opInfo !== null && (int) $opInfo->linen_pool === 1) {
                    $tasks[] = [
                        'task_type' => 'MSA Laundry',
                        'task_date' => $msaDate,
                        'task_price' => $extras ? (float) $extras->fanote_prices : 0.0,
                        'task_user' => $opInfo->linen_supplier_id,
                    ];
                }
                $msaCount = 1;
            } else {
                $msaCount++;
            }
        }

        usort($tasks, function ($a, $b) {
            return strtotime($a['task_date']) <=> strtotime($b['task_date']);
        });

        $allocResponse->data->tasks = $tasks;

        return $this->corsJson($allocResponse, 200);
    }

    public function guestBooking(Request $request)
    {
        $this->assertApiKey($request);

        $body = (object) $request->all();
        $bookingType = $body->booking_type ?? 'booking';

        if (!isset($body->propid, $body->arrival, $body->departure, $body->name, $body->surname, $body->email)) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing required fields'], 400);
        }

        $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $body->propid)->first();
        if (!$prop) {
            return $this->corsJson(['code' => 404, 'message' => 'Property not found'], 404);
        }

        $opInfo = DB::table('virtualdesigns_operationalinformation_operationalinformation')
            ->where('property_id', '=', $body->propid)
            ->first();

        if ($prop->pricelabs_id !== null) {
            if ($bookingType === 'booking') {
                if (isset($body->user_id)) {
                    $userGroup = DB::table('users_groups')->where('user_id', '=', $body->user_id)->first();
                    if ($userGroup && (int) $userGroup->user_group_id === 6) {
                        $channel = 'BookNow.co.za';
                        $haComm = (float) $prop->comm_percent;
                        $tpComm = 17.25;
                        $totalComm = $haComm + $tpComm;
                        $channelShort = 'BN';
                    } else {
                        $channel = 'Host Agents';
                        $haComm = (float) $prop->direct_comm;
                        $tpComm = 0.0;
                        $totalComm = $haComm;
                        $channelShort = 'HA';
                    }
                } else {
                    $channel = 'Host Agents';
                    $haComm = (float) $prop->direct_comm;
                    $tpComm = 0.0;
                    $totalComm = $haComm;
                    $channelShort = 'HA';
                }
            } elseif ($bookingType === 'maintenance') {
                $channel = 'Maintenance';
                $haComm = 0.0;
                $tpComm = 0.0;
                $totalComm = 0.0;
                $channelShort = 'MNT';
            } elseif ($bookingType === 'photo_shoot') {
                $channel = 'Photo Shoot';
                $haComm = 0.0;
                $tpComm = 0.0;
                $totalComm = 0.0;
                $channelShort = 'PHT';
            } elseif ($bookingType === 'deep_clean') {
                $channel = 'Deep Clean';
                $haComm = 0.0;
                $tpComm = 0.0;
                $totalComm = 0.0;
                $channelShort = 'DC';
            } elseif ($bookingType === 'block') {
                $channel = 'Block';
                $haComm = 0.0;
                $tpComm = 0.0;
                $totalComm = 0.0;
                $channelShort = 'BL';
            } else {
                $channel = 'Owner';
                $haComm = 0.0;
                $tpComm = 0.0;
                $totalComm = 0.0;
                $channelShort = 'OB';
            }

            $suburbName = null;
            if ($prop->suburb_id !== null) {
                $suburbName = DB::table('virtualdesigns_locations_locations')->where('id', '=', $prop->suburb_id)->value('name');
            }

            $bookingId = DB::table('virtualdesigns_erpbookings_erpbookings')->insertGetId([
                'property_id' => $body->propid,
                'client_name' => $body->name . ' ' . $body->surname,
                'client_phone' => $body->phone ?? null,
                'client_mobile' => $body->phone ?? null,
                'client_email' => $body->email,
                'arrival_date' => $body->arrival,
                'departure_date' => $body->departure,
                'adults' => $bookingType === 'block' ? 1 : (int) ($body->adults ?? 0),
                'children' => (int) ($body->children ?? 0),
                'payment_notes' => $body->notes ?? null,
                'so_type' => $bookingType,
                'no_guests' => $bookingType === 'block'
                    ? 1
                    : (int) ($body->people ?? ((int) ($body->adults ?? 0) + (int) ($body->children ?? 0))),
                'channel' => $channel,
                'quote_confirmed' => 0,
                'bhr_com' => $haComm,
                'third_party_com' => $tpComm,
                'total_com' => $totalComm,
                'booking_amount' => (float) ($body->totalamount ?? 0),
                'reservationist_notes' => $opInfo ? $opInfo->reservationist_notes : '',
                'suburb' => $suburbName,
                'no_pack' => 0,
                'no_linen' => 0,
                'made_by' => $body->user_id ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $bookingAmount = (float) ($body->totalamount ?? 0);
            if ($bookingAmount <= 0 && $prop->pricelabs_id !== null) {
                try {
                    $priceListsTable = $this->getPriceListsTableName();
                    $availRows = DB::table($priceListsTable)
                        ->where('pl_id', '=', $prop->pricelabs_id)
                        ->where('date', '>=', $body->arrival)
                        ->where('date', '<', $body->departure)
                        ->get();
                } catch (\Throwable $e) {
                    $availRows = collect([]);
                }
                $total = 0.0;
                $currency = 'ZAR';
                foreach ($availRows as $row) {
                    $total += (float) $row->price;
                    $currency = $row->currency;
                }
                if ($currency !== 'ZAR') {
                    $conversion = (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'ZAR/' . $currency)->value('rate');
                    $bookingAmount = $total + ($prop->booking_fee * $conversion) + ($prop->clean_fee * $conversion);
                } else {
                    $bookingAmount = $total + (float) $prop->booking_fee + (float) $prop->clean_fee;
                }
            }

            if ($bookingAmount > 0 && $bookingAmount !== (float) ($body->totalamount ?? 0)) {
                DB::table('virtualdesigns_erpbookings_erpbookings')
                    ->where('id', '=', $bookingId)
                    ->update([
                        'booking_amount' => $bookingAmount,
                        'updated_at' => now(),
                    ]);
            }

            try {
                $priceListsTable = $this->getPriceListsTableName();
                DB::table($priceListsTable)
                    ->where('pl_id', '=', $prop->pricelabs_id)
                    ->where('date', '>=', $body->arrival)
                    ->where('date', '<', $body->departure)
                    ->update(['booked' => 1]);

                $bookedDates = DB::table($priceListsTable)
                    ->where('pl_id', '=', $prop->pricelabs_id)
                    ->where('date', '>=', $body->arrival)
                    ->where('date', '<', $body->departure)
                    ->get();
            } catch (\Throwable $e) {
                $bookedDates = collect([]);
            }

            $xmlAvail = '';
            foreach ($bookedDates as $bookedDate) {
                $xmlAvail .= "<Date From=\"{$bookedDate->date}\" To=\"{$bookedDate->date}\"><U>0</U><MS>{$bookedDate->min_stay}</MS><C>4</C></Date>";
            }

            $xml = "<Push_PutAvbUnits_RQ><Authentication><UserName>book@hostagents.com</UserName><Password>TX@m@Yy6hSUs6N!</Password></Authentication><MuCalendar PropertyID=\"{$prop->rentals_united_id}\">";
            $xml .= $xmlAvail;
            $xml .= '</MuCalendar></Push_PutAvbUnits_RQ>';
            $this->rentalsUnitedRequest($xml);

            if (isset($body->user_id)) {
                $salesPerson = DB::table('users')->where('id', '=', $body->user_id)->first();
                $salesInitials = $salesPerson ? strtoupper($salesPerson->name[0] . $salesPerson->surname[0]) : '';
                $internalRef = $salesInitials . $bookingId . $channelShort;
            } else {
                $internalRef = $bookingId . $channelShort;
            }

            DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->update([
                'booking_ref' => $internalRef,
                'rentalsunited_ref' => null,
                'client_name' => $body->name . ' ' . $body->surname,
                'booking_amount' => $bookingAmount,
                'updated_at' => now(),
            ]);

            if (isset($body->user_id) && ((int) $body->user_id === 1981 || (int) $body->user_id === 1982)) {
                if ($prop->damage_deposit > 0) {
                    $damageId = DB::table('virtualdesigns_erpbookings_damage')->insertGetId([
                        'booking_id' => $bookingId,
                        'property_id' => $body->propid,
                        'internal_ref' => $internalRef . '-BD',
                        'amount' => $prop->damage_deposit,
                        'paid_credit' => 0,
                        'paid_eft' => 0,
                    ]);
                    if ($prop->damage_deposit >= 3000 || (int) $prop->bd_override === 1) {
                        DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->update(['bd_active' => 1]);
                    }
                }
                if ($prop->booking_fee > 0) {
                    DB::table('virtualdesigns_erpbookings_fees')->insert([
                        'description' => 'Booking Fee',
                        'arrival_date' => $body->arrival,
                        'departure_date' => $body->departure,
                        'quantity' => 1,
                        'unit_price' => $prop->booking_fee,
                        'price' => $prop->booking_fee,
                        'mur_unit_price' => $prop->booking_fee,
                        'mur_price' => $prop->booking_fee,
                        'booking_id' => $bookingId,
                    ]);
                }
                if ($prop->clean_fee > 0) {
                    DB::table('virtualdesigns_erpbookings_fees')->insert([
                        'description' => 'Departure Clean',
                        'arrival_date' => $body->arrival,
                        'departure_date' => $body->departure,
                        'quantity' => 1,
                        'unit_price' => $prop->clean_fee,
                        'price' => $prop->clean_fee,
                        'mur_unit_price' => $prop->clean_fee,
                        'mur_price' => $prop->clean_fee,
                        'booking_id' => $bookingId,
                    ]);
                }

                $startTs = strtotime($body->arrival);
                $endTs = strtotime($body->departure);
                $nights = round(($endTs - $startTs) / 86400);
                $bookingLineAmount = $bookingAmount - ($prop->booking_fee + $prop->clean_fee);
                $unitAmount = $nights > 0 ? $bookingLineAmount / $nights : 0;
                DB::table('virtualdesigns_erpbookings_fees')->insert([
                    'description' => '[' . $prop->id . '] ' . $prop->accounting_name,
                    'arrival_date' => $body->arrival,
                    'departure_date' => $body->departure,
                    'quantity' => $nights,
                    'unit_price' => $unitAmount,
                    'price' => $bookingLineAmount,
                    'mur_unit_price' => $unitAmount,
                    'mur_price' => $bookingLineAmount,
                    'booking_id' => $bookingId,
                ]);

                $managerFees = DB::table('virtualdesigns_propertymanagerfees_propertymanagerfees')
                    ->where('property_id', '=', $body->propid)
                    ->first();
                $extras = DB::table('virtualdesigns_extracharges_extracharges')
                    ->where('property_id', '=', $body->propid)
                    ->first();

                $arrivalCleanPrice = $managerFees && $this->isHoliday($body->arrival)
                    ? (float) $managerFees->arrival_clean * 1.5
                    : (float) ($managerFees->arrival_clean ?? 0.0);
                $arrivalConciergePrice = (float) ($managerFees->concierge_fee_arrival ?? 0.0);

                $this->insertCleanTask($body->propid, $bookingId, 'Arrival Clean', $body->arrival, $prop->user_id, $arrivalCleanPrice);
                $this->insertCleanTask($body->propid, $bookingId, 'Concierge Arrival', $body->arrival, $prop->user_id, $arrivalConciergePrice);
                $this->insertCleanTask($body->propid, $bookingId, 'Welcome Pack', $body->arrival, $prop->user_id, (float) ($managerFees->welcome_pack ?? 0.0));

                if ($opInfo !== null && (int) $opInfo->linen_pool === 1 && (int) $opInfo->departure_linen === 0) {
                    $this->insertLaundryTask($body->propid, $bookingId, $opInfo->linen_supplier_id, $body->arrival, $extras ? (float) $extras->fanote_prices : 0.0);
                }

                $departureCleanPrice = $managerFees && $this->isHoliday($body->departure)
                    ? (float) $managerFees->departure_clean * 1.5
                    : (float) ($managerFees->departure_clean ?? 0.0);
                $departureConciergePrice = $managerFees && $this->isHoliday($body->departure)
                    ? (float) $managerFees->concierge_fee_departure * 1.5
                    : (float) ($managerFees->concierge_fee_departure ?? 0.0);

                $this->insertCleanTask($body->propid, $bookingId, 'Departure Clean', $body->departure, $prop->user_id, $departureCleanPrice);
                $this->insertCleanTask($body->propid, $bookingId, 'Concierge Departure', $body->departure, $prop->user_id, $departureConciergePrice);

                if ($opInfo !== null && (int) $opInfo->linen_pool === 1 && (int) $opInfo->departure_linen === 1) {
                    $this->insertLaundryTask($body->propid, $bookingId, $opInfo->linen_supplier_id, $body->departure, $extras ? (float) $extras->fanote_prices : 0.0);
                }
            } else {
                if (!empty($body->fees)) {
                    foreach ($body->fees as $fee) {
                        if ($fee->description === 'Breakage Deposit') {
                            DB::table('virtualdesigns_erpbookings_damage')->insert([
                                'booking_id' => $bookingId,
                                'property_id' => $body->propid,
                                'internal_ref' => $internalRef . '-BD',
                                'amount' => $fee->price,
                                'paid_credit' => 0,
                                'paid_eft' => 0,
                            ]);
                            if ($fee->price >= 3000 || (int) $prop->bd_override === 1) {
                                DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->update(['bd_active' => 1]);
                            }
                        } else {
                            $lineMur = 0.0;
                            $priceListsTable = $this->getPriceListsTableName();
                            $currency = DB::table($priceListsTable)
                                ->where('pl_id', '=', $prop->pricelabs_id)
                                ->value('currency');
                            if ((int) $prop->country_id === 846) {
                                $lineMur = (float) $fee->unit_price;
                            } else {
                                if ($currency === 'EUR') {
                                    $rate = (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'EUR/MUR')->value('rate');
                                    $lineMur = $fee->unit_price * $rate;
                                }
                                if ($currency === 'USD') {
                                    $rate = (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'USD/MUR')->value('rate');
                                    $lineMur = $fee->unit_price * $rate;
                                }
                                if ($currency === 'ZAR') {
                                    $usdRate = (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'ZAR/USD')->value('rate');
                                    $murRate = (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'USD/MUR')->value('rate');
                                    $lineMur = ($fee->unit_price * $usdRate) * $murRate;
                                }
                            }
                            DB::table('virtualdesigns_erpbookings_fees')->insert([
                                'description' => $fee->description,
                                'arrival_date' => $body->arrival,
                                'departure_date' => $body->departure,
                                'quantity' => $fee->quantity,
                                'unit_price' => $fee->unit_price,
                                'price' => $fee->price,
                                'mur_unit_price' => $lineMur,
                                'mur_price' => $lineMur * $fee->quantity,
                                'booking_id' => $bookingId,
                            ]);
                        }
                    }
                }

                if ($bookingType === 'booking' && (int) $prop->country_id === 854) {
                    $startTs = strtotime($body->arrival);
                    $endTs = strtotime($body->departure);
                    $nights = round(($endTs - $startTs) / 86400);
                    $tax = ($prop->bedroom_num * 10) * min($nights, 30);
                    DB::table('virtualdesigns_erpbookings_fees')->insert([
                        'description' => 'Tourist Tax',
                        'arrival_date' => $body->arrival,
                        'departure_date' => $body->departure,
                        'quantity' => 1,
                        'unit_price' => $tax,
                        'price' => $tax,
                        'mur_unit_price' => $tax,
                        'mur_price' => $tax,
                        'booking_id' => $bookingId,
                    ]);
                }

                if (!empty($body->tasks)) {
                    foreach ($body->tasks as $task) {
                        if (!empty($task->selected)) {
                            if (strpos($task->task_type, 'Laundry') !== false) {
                                $this->insertLaundryTask($body->propid, $bookingId, $task->task_user, $task->task_date, $task->task_price);
                                $this->pushNotify($task->task_user, $bookingId, 'laundry');
                            } else {
                                $this->insertCleanTask($body->propid, $bookingId, $task->task_type, $task->task_date, $task->task_user, $task->task_price);
                                $this->pushNotify($task->task_user, $bookingId, 'task');
                            }
                        }
                    }
                }
            }

            $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->first();
            return $this->corsJson($booking, 200);
        }

        $siteSettings = DB::table('virtualdesigns_settings_settings')->first();
        if (!$siteSettings || (int) $siteSettings->nb_active !== 1) {
            return $this->corsJson('Nightsbridge disabled', 400);
        }

        $nbBookingUrl = 'https://www.nightsbridge.co.za/bridge/api/5.0/channel/633/booking/' . $prop->nb_id;
        $guestPhone = (!empty($body->phone) && is_string($body->phone)) ? $body->phone : '000000000';

        $bookingPayload = [
            'startdate' => date('Y-m-d', strtotime($body->arrival)),
            'enddate' => date('Y-m-d', strtotime($body->departure)),
            'title' => '',
            'firstname' => $body->name,
            'surname' => $body->surname,
            'email' => $body->email,
            'phoneno' => $guestPhone,
            'deposit' => $body->deposit ?? 0,
            'totalamount' => $body->totalamount ?? 0,
            'notes' => $body->notes ?? '',
            'roomtypes' => $body->roomtypes ?? [],
        ];

        $bookingResponse = $this->nightsbridgePost($nbBookingUrl, $bookingPayload);
        if (!isset($bookingResponse->success) || $bookingResponse->success !== 'true') {
            return $this->corsJson(json_encode($bookingResponse->error ?? 'Nightsbridge booking failed'), 400);
        }

        if ($bookingType === 'booking') {
            $channel = 'Host Agents';
            $haComm = (float) $prop->direct_comm;
            $tpComm = 0.0;
            $totalComm = $haComm;
            $channelShort = 'HA';
        } elseif ($bookingType === 'maintenance') {
            $channel = 'Maintenance';
            $haComm = 0.0;
            $tpComm = 0.0;
            $totalComm = 0.0;
            $channelShort = 'MNT';
        } elseif ($bookingType === 'photo_shoot') {
            $channel = 'Photo Shoot';
            $haComm = 0.0;
            $tpComm = 0.0;
            $totalComm = 0.0;
            $channelShort = 'PHT';
        } elseif ($bookingType === 'deep_clean') {
            $channel = 'Deep Clean';
            $haComm = 0.0;
            $tpComm = 0.0;
            $totalComm = 0.0;
            $channelShort = 'DC';
        } elseif ($bookingType === 'block') {
            $channel = 'Block';
            $haComm = 0.0;
            $tpComm = 0.0;
            $totalComm = 0.0;
            $channelShort = 'BL';
        } else {
            $channel = 'Owner';
            $haComm = 0.0;
            $tpComm = 0.0;
            $totalComm = 0.0;
            $channelShort = 'OB';
        }

        if (isset($body->user_id)) {
            $salesPerson = DB::table('users')->where('id', '=', $body->user_id)->first();
            $salesInitials = $salesPerson ? strtoupper($salesPerson->name[0] . $salesPerson->surname[0]) : '';
            $internalRef = $salesInitials . $bookingResponse->data->booking->bookingid . $channelShort;
        } else {
            $internalRef = $bookingResponse->data->booking->bookingid . $channelShort;
        }

        $suburbName = null;
        if ($prop->suburb_id !== null) {
            $suburbName = DB::table('virtualdesigns_locations_locations')->where('id', '=', $prop->suburb_id)->value('name');
        }

        $bookingId = DB::table('virtualdesigns_erpbookings_erpbookings')->insertGetId([
            'property_id' => $body->propid,
            'client_name' => $body->name . ' ' . $body->surname,
            'client_phone' => $body->phone ?? null,
            'client_mobile' => $body->phone ?? null,
            'client_email' => $body->email,
            'arrival_date' => $body->arrival,
            'departure_date' => $body->departure,
            'booking_ref' => $internalRef,
            'adults' => $body->adults ?? 0,
            'children' => $body->children ?? 0,
            'payment_notes' => $body->notes ?? null,
            'so_type' => $bookingType,
            'no_guests' => $body->people ?? ((int) ($body->adults ?? 0) + (int) ($body->children ?? 0)),
            'channel' => $channel,
            'quote_confirmed' => 0,
            'bhr_com' => $haComm,
            'third_party_com' => $tpComm,
            'total_com' => $totalComm,
            'booking_amount' => $body->totalamount ?? 0,
            'reservationist_notes' => $opInfo ? $opInfo->reservationist_notes : '',
            'suburb' => $suburbName,
            'no_pack' => 0,
            'no_linen' => 0,
            'made_by' => $body->user_id ?? null,
            'nightsbridge_ref' => $bookingResponse->data->booking->bookingid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->first();
        return $this->corsJson($booking, 200);
    }

    public function confirmBooking(Request $request)
    {
        $this->assertApiKey($request);

        $bookingId = $request->header('bookingid');
        if ($bookingId === null) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing bookingid header'], 400);
        }

        $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->first();
        if (!$booking) {
            return $this->corsJson(['code' => 404, 'message' => 'Booking not found'], 404);
        }

        if ($booking->channel === null) {
            return $this->corsJson(['code' => 500, 'message' => 'Please set channel before confirming booking'], 500);
        }

        if ((int) $booking->quote_confirmed !== 1) {
            DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->update([
                'date_confirmed' => now(),
                'quote_confirmed' => 1,
                'updated_at' => now(),
            ]);
        }

        $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $booking->property_id)->first();
        $opInfo = DB::table('virtualdesigns_operationalinformation_operationalinformation')
            ->where('property_id', '=', $booking->property_id)
            ->first();
        $managerFees = DB::table('virtualdesigns_propertymanagerfees_propertymanagerfees')
            ->where('property_id', '=', $booking->property_id)
            ->first();
        $extras = DB::table('virtualdesigns_extracharges_extracharges')
            ->where('property_id', '=', $booking->property_id)
            ->first();

        $taskCount = DB::table('virtualdesigns_cleans_cleans')
            ->where('booking_id', '=', $booking->id)
            ->where('status', '=', 0)
            ->count();

        if ($booking->rentalsunited_ref !== null && $taskCount === 0 && $prop) {
            $arrivalCleanPrice = $managerFees && $this->isHoliday($booking->arrival_date)
                ? (float) $managerFees->arrival_clean * 1.5
                : (float) ($managerFees->arrival_clean ?? 0.0);
            $arrivalConciergePrice = (float) ($managerFees->concierge_fee_arrival ?? 0.0);

            $this->insertCleanTask($prop->id, $booking->id, 'Arrival Clean', $booking->arrival_date, $prop->user_id, $arrivalCleanPrice);
            $this->insertCleanTask(
                $prop->id,
                $booking->id,
                'Concierge Arrival',
                $booking->arrival_date,
                $prop->concierge_id ?? $prop->user_id,
                $arrivalConciergePrice
            );
            $this->insertCleanTask($prop->id, $booking->id, 'Welcome Pack', $booking->arrival_date, $prop->user_id, (float) ($managerFees->welcome_pack ?? 0.0));

            if ($opInfo !== null && (int) $opInfo->linen_pool === 1 && (int) $opInfo->departure_linen === 0) {
                $this->insertLaundryTask($prop->id, $booking->id, $opInfo->linen_supplier_id, $booking->arrival_date, $extras ? (float) $extras->fanote_prices : 0.0);
            }

            $departureCleanPrice = $managerFees && $this->isHoliday($booking->departure_date)
                ? (float) $managerFees->departure_clean * 1.5
                : (float) ($managerFees->departure_clean ?? 0.0);
            $departureConciergePrice = $managerFees && $this->isHoliday($booking->departure_date)
                ? (float) $managerFees->concierge_fee_departure * 1.5
                : (float) ($managerFees->concierge_fee_departure ?? 0.0);

            $this->insertCleanTask($prop->id, $booking->id, 'Departure Clean', $booking->departure_date, $prop->user_id, $departureCleanPrice);
            $this->insertCleanTask(
                $prop->id,
                $booking->id,
                'Concierge Departure',
                $booking->departure_date,
                $prop->concierge_id ?? $prop->user_id,
                $departureConciergePrice
            );

            if ($opInfo !== null && (int) $opInfo->linen_pool === 1 && (int) $opInfo->departure_linen === 1) {
                $this->insertLaundryTask($prop->id, $booking->id, $opInfo->linen_supplier_id, $booking->departure_date, $extras ? (float) $extras->fanote_prices : 0.0);
            }

            $msaCount = 1;
            $msaDate = $booking->arrival_date;
            $hasFirst = false;
            while (strtotime($msaDate) < strtotime($booking->departure_date)) {
                $msaDate = date('Y-m-d', strtotime($msaDate . '+ 1 day'));
                if ($msaCount === 8) {
                    if ($hasFirst) {
                        $msaDate = date('Y-m-d', strtotime($msaDate . '- 4 day'));
                    } else {
                        $msaDate = date('Y-m-d', strtotime($msaDate . '- 5 day'));
                        $hasFirst = true;
                    }
                    $this->insertCleanTask($prop->id, $booking->id, 'MSA', $msaDate, $prop->user_id, (float) ($managerFees->mid_stay_clean ?? 0.0));
                    if ($opInfo !== null && (int) $opInfo->linen_pool === 1) {
                        $this->insertLaundryTask($prop->id, $booking->id, $opInfo->linen_supplier_id, $msaDate, $extras ? (float) $extras->fanote_prices : 0.0);
                    }
                    $msaCount = 1;
                } else {
                    $msaCount++;
                }
            }
        }

        if (strtotime($booking->arrival_date) === strtotime(date('Y-m-d'))) {
            $this->sendWelcomeLetter($booking);
        }

        return $this->corsJson('Success', 200);
    }

    public function cancelBooking(Request $request)
    {
        $this->assertApiKey($request);

        $bookingId = $request->header('bookingid');
        if ($bookingId === null) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing bookingid header'], 400);
        }

        $userId = $request->header('userid');

        $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->first();
        if (!$booking) {
            return $this->corsJson(['code' => 404, 'message' => 'Booking not found'], 404);
        }

        $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $booking->property_id)->first();
        $tasks = DB::table('virtualdesigns_cleans_cleans')
            ->where('booking_id', '=', $bookingId)
            ->where('clean_date', '>=', date('Y-m-d'))
            ->get();
        $laundries = DB::table('virtualdesigns_laundry_laundry')
            ->where('booking_id', '=', $bookingId)
            ->where('action_date', '>=', date('Y-m-d'))
            ->get();

        if ($prop && $prop->pricelabs_id !== null) {
            try {
                $priceListsTable = $this->getPriceListsTableName();
                DB::table($priceListsTable)
                    ->where('date', '>=', $booking->arrival_date)
                    ->where('date', '<', $booking->departure_date)
                    ->where('pl_id', $prop->pricelabs_id)
                    ->update(['booked' => 0]);
            } catch (\Throwable $e) {
                // ignore if table not present in this connection
            }
        }

        $refundRequired = $request->input('refund_required') == 1 ? 1 : 0;

        DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->update([
            'status' => 1,
            'pending_cancel' => 1,
            'reason_cancelled' => $request->input('cancel_reason'),
            'date_cancelled' => now(),
            'cancelled_by' => $userId,
            'refund_required' => $refundRequired,
            'updated_at' => now(),
        ]);

        foreach ($tasks as $task) {
            DB::table('virtualdesigns_cleans_cleans')->where('id', '=', $task->id)->update(['status' => 1]);
        }

        foreach ($laundries as $laundry) {
            DB::table('virtualdesigns_laundry_laundry')->where('id', '=', $laundry->id)->update(['status' => 1]);
        }

        return $this->corsJson(['code' => 200, 'message' => 'Booking Cancelled'], 200);
    }

    public function guestDetails(Request $request)
    {
        $this->assertApiKey($request);

        $bookingId = $request->header('bookingid') ?? $request->input('bookingid');
        if ($bookingId === null) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing bookingid'], 400);
        }

        $guestData = DB::table('virtualdesigns_erpbookings_guestinfo')
            ->where('booking_id', '=', $bookingId)
            ->select(
                'id',
                'guest_name',
                'guest_id_no',
                'guest_contact',
                'guest_no',
                'eta',
                'etd',
                'flight_number',
                'bank_ac_name',
                'bank_ac_no',
                'bank_name',
                'bank_code',
                'no_smoking',
                'noise_policy',
                'fair_usage_policy',
                'breakage_policy',
                'terms_conditions',
                'booking_id',
                'bank_type',
                'swift_code',
                'pay_type',
                'guest_alternative_email_address',
                'guest_id as guest_id_doc',
                'other_guests_data'
            )
            ->first();

        if ($guestData !== null) {
            $guestData->no_smoking = (int) $guestData->no_smoking;
            $guestData->noise_policy = (int) $guestData->noise_policy;
            $guestData->fair_usage_policy = (int) $guestData->fair_usage_policy;
            $guestData->breakage_policy = (int) $guestData->breakage_policy;
            $guestData->terms_conditions = (int) $guestData->terms_conditions;
        }

        return $this->corsJson($guestData, 200);
    }

    public function UpdateGuestDetails(Request $request)
    {
        $this->assertApiKey($request);

        $payload = (object) $request->all();
        if (!isset($payload->id)) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing id'], 400);
        }

        $guestData = DB::table('virtualdesigns_erpbookings_guestinfo')->where('id', '=', $payload->id)->first();
        if (!$guestData) {
            return $this->corsJson(['code' => 404, 'message' => 'Guest record not found'], 404);
        }

        $changeUser = $request->input('change_user');
        if ($changeUser !== null) {
            $fields = [
                'guest_name',
                'guest_id_no',
                'guest_contact',
                'guest_no',
                'eta',
                'etd',
                'flight_number',
                'bank_ac_name',
                'bank_ac_no',
                'bank_name',
                'bank_code',
                'no_smoking',
                'noise_policy',
                'fair_usage_policy',
                'breakage_policy',
                'terms_conditions',
                'bank_type',
                'swift_code',
                'pay_type',
                'guest_alternative_email_address',
                'guest_id',
                'other_guests_data',
            ];

            foreach ($fields as $field) {
                if (property_exists($payload, $field) && $guestData->{$field} != $payload->{$field}) {
                    DB::table('virtualdesigns_changes_changes')->insert([
                        'user_id' => $changeUser,
                        'db_table' => 'virtualdesigns_erpbookings_guestinfo',
                        'record_id' => $guestData->id,
                        'field' => $field,
                        'old' => $guestData->{$field},
                        'new' => $payload->{$field},
                        'change_date' => now(),
                    ]);
                }
            }
        }

        $updateFields = [
            'guest_name',
            'guest_id_no',
            'guest_contact',
            'guest_no',
            'eta',
            'etd',
            'flight_number',
            'bank_ac_name',
            'bank_ac_no',
            'bank_name',
            'bank_code',
            'no_smoking',
            'noise_policy',
            'fair_usage_policy',
            'breakage_policy',
            'terms_conditions',
            'bank_type',
            'swift_code',
            'pay_type',
            'guest_alternative_email_address',
            'guest_id',
            'other_guests_data',
        ];

        $updates = [];
        foreach ($updateFields as $field) {
            if (property_exists($payload, $field)) {
                $updates[$field] = $payload->{$field};
            }
        }

        if (!empty($updates)) {
            DB::table('virtualdesigns_erpbookings_guestinfo')->where('id', '=', $payload->id)->update($updates);
        }

        $guestData = DB::table('virtualdesigns_erpbookings_guestinfo')->where('id', '=', $payload->id)->first();

        return $this->corsJson($guestData, 200);
    }

    public function getBillingData(Request $request, $bookingId)
    {
        $this->assertApiKey($request);

        try {
            $fees = DB::table('virtualdesigns_erpbookings_fees')->where('booking_id', '=', $bookingId)->get()->toArray();
            $damage = DB::table('virtualdesigns_erpbookings_damage')->where('booking_id', '=', $bookingId)->take(1)->get()->toArray();
            $channels = DB::table('virtualdesigns_channels_providers')->get()->toArray();
            $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->first();
            $guestData = DB::table('virtualdesigns_erpbookings_guestinfo')->where('booking_id', '=', $bookingId)->first();

            if ($guestData !== null) {
                $guestData->no_smoking = (int) $guestData->no_smoking;
                $guestData->noise_policy = (int) $guestData->noise_policy;
                $guestData->fair_usage_policy = (int) $guestData->fair_usage_policy;
                $guestData->breakage_policy = (int) $guestData->breakage_policy;
                $guestData->terms_conditions = (int) $guestData->terms_conditions;
            }

            $balanceDue = $booking->balance_due ?? null;
            $bdActive = $booking->bd_active ?? null;

            $invoice = null;
            if (isset($booking->guest_invoice)) {
                $propRec = DB::table('virtualdesigns_properties_properties')->where('id', '=', $booking->property_id)->first();
                if ($propRec && (int) $propRec->country_id === 846) {
                    $invoice = 'https://go.xero.com/app/!b6sP0/invoicing/view/' . $booking->guest_invoice;
                } else {
                    $invoice = 'https://go.xero.com/app/!25F0!/invoicing/view/' . $booking->guest_invoice;
                }
            }

            $response = [
                'fees' => $fees,
                'damage' => $damage,
                'channels' => $channels,
                'balance_due' => $balanceDue,
                'bd_active' => $bdActive,
                'guest_invoice' => $invoice,
                'guest_data' => $guestData,
                'non_refundable' => $booking->non_refundable ?? null,
                'vc_date' => $booking->vc_date ?? null,
            ];

            return $this->corsJson($response, 200);
        } catch (\Throwable $th) {
            return $this->corsJson($th->getMessage(), 500);
        }
    }

    public function getMails(Request $request, $bookingId)
    {
        try {
            $mails = DB::table('virtualdesigns_bookings_mails')
                ->where('virtualdesigns_bookings_mails.booking_id', $bookingId)
                ->join('users', 'virtualdesigns_bookings_mails.user_id', '=', 'users.id')
                ->select('virtualdesigns_bookings_mails.*', 'users.name as user_name')
                ->orderBy('created_at', 'DESC')
                ->get();

            return $this->corsJson($mails, 200);
        } catch (\Throwable $th) {
            return $this->corsJson($th->getMessage(), 500);
        }
    }

    public function sendMail(Request $request, $id)
    {
        $this->assertApiKey($request);

        $payload = $request->all();
        $templateName = $request->input('template_name');
        if ($templateName === null) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing template_name'], 400);
        }

        $messageType = $payload['message_type'] ?? 1;
        if ((int) $messageType === 2) {
            return $this->corsJson(['code' => 200, 'message' => 'Whatsapp message recorded'], 200);
        }

        $userId = $request->header('userid') ?? $request->header('Userid') ?? $payload['user_id'] ?? null;
        $mailVars = $payload['mail_vars'] ?? $payload;

        DB::table('virtualdesigns_bookings_mails')->insert([
            'booking_id' => $id,
            'user_id' => $userId,
            'template_name' => $templateName,
            'message_type' => $messageType,
            'mail_vars' => json_encode($mailVars),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($templateName === 'Damage Deposit Template') {
            DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $id)->update(['damage_sent' => 1]);
        }

        return $this->corsJson(['code' => 200, 'message' => 'Recorded'], 200);
    }

    public function getReflist(Request $request, $bookingRef)
    {
        $this->assertApiKey($request);

        try {
            $refs = DB::table('virtualdesigns_erpbookings_erpbookings')
                ->where('booking_ref', 'like', '%' . $bookingRef . '%')
                ->pluck('booking_ref');

            return $this->corsJson($refs, 200);
        } catch (\Throwable $th) {
            return $this->corsJson($th->getMessage(), 500);
        }
    }

    public function requestChanges(Request $request)
    {
        $this->assertApiKey($request);

        $bookingId = $request->input('booking_id');
        if ($bookingId === null) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing booking_id'], 400);
        }

        $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->first();
        if (!$booking) {
            return $this->corsJson(['code' => 404, 'message' => 'Booking not found'], 404);
        }

        $notes = $request->input('notes');

        DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->update([
            'pending_update' => 1,
            'updated_at' => now(),
        ]);

        $changes = [];
        $fields = [
            'property_id',
            'channel',
            'arrival_date',
            'departure_date',
            'adults',
            'children',
            'booking_amount',
            'client_name',
            'client_phone',
            'client_mobile',
            'client_email',
        ];

        foreach ($fields as $field) {
            if ($request->has($field)) {
                $changes[$field] = [
                    'old' => $booking->{$field},
                    'new' => $request->input($field),
                ];
            }
        }

        $logId = DB::table('virtualdesigns_nightsbridgewebhook_logs')->insertGetId([
            'mode' => 'update',
            'status' => 'Success-Website',
            'message' => json_encode($changes),
            'booking_id' => $bookingId,
            'notes' => $notes,
            'completed' => 0,
        ]);

        $log = DB::table('virtualdesigns_nightsbridgewebhook_logs')->where('id', '=', $logId)->first();

        return $this->corsJson($log, 200);
    }

    public function confirmChanges(Request $request)
    {
        $this->assertApiKey($request);

        $type = $request->header('type') ?? $request->input('type');
        $notes = $request->input('notes');

        if ($type === 'cancel') {
            $bookingId = $request->input('bid') ?? $request->input('booking_id');
            if ($bookingId === null) {
                return $this->corsJson(['code' => 400, 'message' => 'Missing booking id'], 400);
            }

            $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->first();
            if (!$booking) {
                return $this->corsJson(['code' => 404, 'message' => 'Booking not found'], 404);
            }

            $tasks = DB::table('virtualdesigns_cleans_cleans')
                ->where('booking_id', '=', $bookingId)
                ->where('clean_date', '>=', date('Y-m-d'))
                ->get();
            $laundries = DB::table('virtualdesigns_laundry_laundry')
                ->where('booking_id', '=', $bookingId)
                ->where('action_date', '>=', date('Y-m-d'))
                ->get();

            DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->update([
                'status' => 1,
                'pending_cancel' => 0,
                'reason_cancelled' => $request->input('reason'),
                'date_cancelled' => $booking->date_cancelled ?? now(),
                'updated_at' => now(),
            ]);

            foreach ($tasks as $task) {
                DB::table('virtualdesigns_cleans_cleans')->where('id', '=', $task->id)->update(['status' => 1]);
            }

            foreach ($laundries as $laundry) {
                DB::table('virtualdesigns_laundry_laundry')->where('id', '=', $laundry->id)->update(['status' => 1]);
            }

            return $this->corsJson(['code' => 200, 'message' => 'Success'], 200);
        }

        if ($type === 'update') {
            $bookingId = $request->input('bid') ?? $request->input('booking_id');
            $changeId = $request->input('cid');

            if ($bookingId !== null) {
                DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->update([
                    'pending_update' => 0,
                    'updated_at' => now(),
                ]);
            }

            if ($changeId !== null) {
                DB::table('virtualdesigns_nightsbridgewebhook_logs')->where('id', '=', $changeId)->update([
                    'completed' => 1,
                    'notes' => $notes,
                ]);
            }

            return $this->corsJson(['code' => 200, 'message' => 'Success'], 200);
        }

        return $this->corsJson(['code' => 400, 'message' => 'Invalid change type'], 400);
    }

    public function getChanges(Request $request)
    {
        $this->assertApiKey($request);

        $type = $request->header('type') ?? $request->input('type') ?? 'updates';

        try {
            if ($type === 'updates') {
                $data = DB::table('virtualdesigns_erpbookings_erpbookings as booking')
                    ->join('virtualdesigns_properties_properties as property', 'booking.property_id', '=', 'property.id')
                    ->join('virtualdesigns_nightsbridgewebhook_logs as log', function ($join) {
                        $join->on('booking.id', '=', 'log.booking_id')
                            ->where('log.mode', '=', 'update')
                            ->where('log.completed', '=', 0);
                    })
                    ->where('booking.pending_update', '=', 1)
                    ->select(
                        'booking.*',
                        'log.message as changes',
                        'log.completed',
                        'log.id as cid',
                        'log.notes as change_notes',
                        'property.name as property_name'
                    )
                    ->get();

                foreach ($data as $booking) {
                    $changesArray = (array) json_decode($booking->changes);
                    foreach (array_keys($changesArray) as $changeKey) {
                        $label = ucwords(str_replace('_', ' ', $changeKey));
                        $changesArray[$changeKey]->lable = $label;
                    }
                    $booking->changes = json_encode($changesArray);
                }

                return $this->corsJson($data, 200);
            }

            if ($type === 'cancellations') {
                $data = DB::table('virtualdesigns_erpbookings_erpbookings as booking')
                    ->join('virtualdesigns_properties_properties as property', 'booking.property_id', '=', 'property.id')
                    ->where('booking.pending_cancel', '=', 1)
                    ->select('booking.*', 'property.name as property_name')
                    ->get();

                return $this->corsJson($data, 200);
            }

            return $this->corsJson(['code' => 400, 'message' => 'Invalid change type'], 400);
        } catch (\Throwable $th) {
            return $this->corsJson($th->getMessage(), 500);
        }
    }

    public function RaiseSO(Request $request, $id)
    {
        $this->assertApiKey($request);

        $type = $request->input('type', 'booking');
        $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $id)->first();
        if (!$booking) {
            return $this->corsJson(['code' => 404, 'message' => 'Booking not found'], 404);
        }

        $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $booking->property_id)->first();
        $salesperson = $booking->made_by ? DB::table('users')->where('id', '=', $booking->made_by)->first() : null;

        $startTs = strtotime($booking->arrival_date);
        $endTs = strtotime($booking->departure_date);
        $nights = max(1, (int) round(($endTs - $startTs) / 86400));
        $people = (int) $booking->adults + (int) $booking->children;
        $pricePerNight = $nights > 0 ? (float) $booking->booking_amount / $nights : (float) $booking->booking_amount;

        if ($type === 'booking') {
            $tasks = DB::table('virtualdesigns_cleans_cleans')
                ->where('booking_id', '=', $booking->id)
                ->where('status', '=', 0)
                ->whereNull('deleted_at')
                ->get();
            $fees = DB::table('virtualdesigns_erpbookings_fees')
                ->where('booking_id', '=', $booking->id)
                ->get()
                ->toArray();

            foreach ($fees as $fee) {
                $fee->price = $fee->price ?? 0;
                $fee->unit_price = $fee->unit_price ?? 0;
            }

            $clientName = explode(' ', trim((string) $booking->client_name), 2);

            $payload = [
                'db_booking_id' => $booking->id,
                'propid' => $prop ? $prop->id : null,
                'name' => $clientName[0] ?? '',
                'surname' => $clientName[1] ?? '',
                'email' => $booking->client_email,
                'phone' => $booking->client_phone,
                'arrival' => $booking->arrival_date,
                'departure' => $booking->departure_date,
                'nights' => $nights,
                'people' => $people,
                'adults' => $booking->adults,
                'children' => $booking->children,
                'pricePerNight' => $pricePerNight,
                'notes' => $booking->payment_notes,
                'channel' => $booking->channel,
                'fees' => $fees,
                'salesperson_name' => $salesperson ? $salesperson->name . ' ' . $salesperson->surname : null,
                'salesperson_email' => $salesperson ? $salesperson->email : null,
                'user_id' => $booking->made_by,
                'booking_type' => $booking->so_type,
                'internal_ref' => $booking->booking_ref,
                'ha_comm' => $booking->bhr_com,
                'tp_comm' => $booking->third_party_com,
                'total_comm' => $booking->total_com,
                'tasks' => $tasks,
                'welcome_pack' => $booking->no_pack,
                'linen' => $booking->no_linen,
                'virtual_card' => $booking->virtual_card,
                'mode' => 'create',
            ];

            return $this->corsJson(['booking' => $booking, 'payload' => $payload], 200);
        }

        if ($type === 'damage') {
            $damage = DB::table('virtualdesigns_erpbookings_damage')->where('booking_id', '=', $booking->id)->first();
            $guestDetails = DB::table('virtualdesigns_erpbookings_guestinfo')->where('booking_id', '=', $booking->id)->first();
            $clientName = explode(' ', trim((string) $booking->client_name), 2);

            $payload = [
                'propid' => $booking->property_id,
                'name' => $clientName[0] ?? '',
                'surname' => $clientName[1] ?? '',
                'email' => $booking->client_email,
                'phone' => $booking->client_phone,
                'arrival' => $booking->arrival_date,
                'departure' => $booking->departure_date,
                'people' => $people,
                'adults' => $booking->adults,
                'children' => $booking->children,
                'notes' => $booking->booking_notes,
                'channel' => $booking->channel,
                'salesperson_name' => $salesperson ? $salesperson->name . ' ' . $salesperson->surname : null,
                'salesperson_email' => $salesperson ? $salesperson->email : null,
                'user_id' => $booking->made_by,
                'internal_ref' => $booking->booking_ref . '-BD',
                'amount' => $damage ? $damage->amount : null,
                'bank_ac_name' => $guestDetails->bank_ac_name ?? null,
                'bank_ac_no' => $guestDetails->bank_ac_no ?? null,
                'bank_name' => $guestDetails->bank_name ?? null,
                'bank_code' => $guestDetails->bank_code ?? null,
                'bank_type' => $guestDetails->bank_type ?? null,
                'mode' => 'damage',
                'damage_id' => $damage ? $damage->id : null,
            ];

            return $this->corsJson(['booking' => $booking, 'payload' => $payload], 200);
        }

        return $this->corsJson(['code' => 400, 'message' => 'Invalid type'], 400);
    }

    public function linkSo(Request $request, $id)
    {
        $this->assertApiKey($request);

        $type = $request->input('type');
        $soNumber = $request->input('so_number');
        if ($type === null || $soNumber === null) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing type or so_number'], 400);
        }

        if ($type === 'booking') {
            DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $id)->update([
                'so_number' => $soNumber,
                'updated_at' => now(),
            ]);
            $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $id)->first();
            return $this->corsJson($booking, 200);
        }

        if ($type === 'damage') {
            DB::table('virtualdesigns_erpbookings_damage')->where('booking_id', '=', $id)->update([
                'so_number' => $soNumber,
            ]);
            $damage = DB::table('virtualdesigns_erpbookings_damage')->where('booking_id', '=', $id)->first();
            return $this->corsJson($damage, 200);
        }

        return $this->corsJson(['code' => 400, 'message' => 'Invalid type'], 400);
    }

    public function NightsbridgeUpdate(Request $request, $id)
    {
        $this->assertApiKey($request);

        $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $id)->first();
        if (!$booking) {
            return $this->corsJson(['code' => 404, 'message' => 'Booking not found'], 404);
        }

        if (!empty($booking->nightsbridge_ref)) {
            try {
                Http::timeout(15)->get('https://hostagents.co.za/' . $booking->nightsbridge_ref . '?mode=update');
            } catch (\Throwable $th) {
            }
        }

        return $this->corsJson($booking, 200);
    }

    public function allbookings(Request $request)
    {
        $this->assertApiKey($request);

        $userId = $request->header('userid');
        if ($userId === null) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing userid'], 400);
        }

        $quotesOnly = $request->header('quotesonly');
        $groupId = DB::table('users_groups')->where('user_id', '=', $userId)->value('user_group_id');

        $baseQuery = DB::table('virtualdesigns_erpbookings_erpbookings as booking')
            ->leftJoin('virtualdesigns_properties_properties as property', 'booking.property_id', '=', 'property.id')
            ->leftJoin('users as salesperson', 'booking.made_by', '=', 'salesperson.id')
            ->leftJoin('users as manager', 'property.user_id', '=', 'manager.id')
            ->leftJoin('users as cancelled_by', 'booking.cancelled_by', '=', 'cancelled_by.id')
            ->leftJoin('virtualdesigns_locations_locations as suburb', 'property.suburb_id', '=', 'suburb.id')
            ->leftJoin('virtualdesigns_erpbookings_guestinfo as guestinfo', 'guestinfo.booking_id', '=', 'booking.id')
            ->whereNull('booking.deleted_at');

        if ((int) $groupId === 1) {
            $baseQuery->where('property.owner_id', '=', $userId);
            $baseQuery->where('booking.quote_confirmed', '=', 1);
        } elseif ((int) $groupId === 3) {
            $baseQuery->where('property.user_id', '=', $userId);
        } elseif ((int) $groupId === 5) {
            $baseQuery->where('property.bodycorp_id', '=', $userId);
        } elseif ((int) $groupId === 2) {
            if ($request->header('cancelledquotes')) {
                $baseQuery->where('booking.status', '=', 1)->where('booking.quote_confirmed', '!=', 1);
                if ($request->header('cancelstart')) {
                    $baseQuery->where('booking.date_cancelled', '>=', $request->header('cancelstart') . ' 00:00:00');
                }
                if ($request->header('cancelend')) {
                    $baseQuery->where('booking.date_cancelled', '<=', $request->header('cancelend') . ' 23:59:59');
                }
            }
        }

        if ($quotesOnly === 'true') {
            $baseQuery->where('booking.quote_confirmed', '!=', 1)
                ->where('booking.status', '!=', 1);
        }

        if ($request->header('propid')) {
            $baseQuery->where('booking.property_id', '=', $request->header('propid'));
        }

        if ($request->header('arrivalstart')) {
            $baseQuery->where('booking.arrival_date', '>=', date('Y-m-d', strtotime($request->header('arrivalstart'))));
        }
        if ($request->header('arrivalend')) {
            $baseQuery->where('booking.arrival_date', '<=', date('Y-m-d', strtotime($request->header('arrivalend'))));
        }
        if ($request->header('todayarrival')) {
            $baseQuery->where('booking.arrival_date', '=', date('Y-m-d', strtotime($request->header('todayarrival'))));
        }
        if ($request->header('todaydeparture')) {
            $baseQuery->where('booking.departure_date', '=', date('Y-m-d', strtotime($request->header('todaydeparture'))));
        }
        if ($request->header('istoday')) {
            $today = date('Y-m-d');
            $baseQuery->where('booking.arrival_date', '<', $today)->where('booking.departure_date', '>', $today);
        }

        $bookings = $baseQuery->select(
            'booking.id',
            'booking.bd_active',
            'booking.booking_ref',
            'booking.arrival_date',
            'booking.departure_date',
            'booking.virtual_card',
            'booking.balance_due',
            'booking.guest_invoice',
            'booking.payment_notes',
            'booking.so_type',
            'property.name as prop_name',
            'property.accounting_name as accounting_name',
            'property.country_id as country_id',
            'booking.client_name',
            'booking.channel',
            'booking.booking_amount',
            'booking.created_at as date_quoted',
            'booking.date_confirmed',
            'booking.quote_confirmed',
            'salesperson.name as salesperson_name',
            'salesperson.surname as salesperson_surname',
            'booking.status',
            'suburb.name as suburb',
            'manager.name as manager_name',
            'manager.surname as manager_surname',
            'cancelled_by.name as cancelled_by_name',
            'cancelled_by.surname as cancelled_by_surname',
            'booking.so_number',
            'booking.client_phone',
            'booking.client_mobile',
            'booking.client_email',
            'booking.booking_notes',
            'booking.room_name',
            'booking.pay_on_arrival',
            'booking.no_review',
            'booking.no_linen',
            'booking.no_pack',
            'booking.website_from',
            'booking.total_com',
            'booking.bhr_com',
            'booking.third_party_com',
            'property.id as property_id',
            'property.booking_fee as booking_fee',
            'property.clean_fee as departure_fee',
            'guestinfo.bank_ac_name',
            'guestinfo.bank_ac_no',
            'guestinfo.bank_name',
            'guestinfo.bank_code',
            'guestinfo.bank_type',
            'guestinfo.swift_code',
            'guestinfo.guest_id as guest_id_doc'
        )->get();

        foreach ($bookings as $booking) {
            $booking->cancelled_by = trim(($booking->cancelled_by_name ?? '') . ' ' . ($booking->cancelled_by_surname ?? ''));
            $booking->paid = ($booking->balance_due ?? 0) <= 0 ? 1 : 0;
            $booking->processed = $booking->guest_invoice ? 1 : 0;
            $startTs = strtotime($booking->arrival_date);
            $endTs = strtotime($booking->departure_date);
            $booking->nights = (int) round(($endTs - $startTs) / 86400);
            if ((int) $booking->quote_confirmed !== 1 && (int) $booking->status !== 1) {
                $startTs = strtotime($booking->date_quoted);
                $endTs = strtotime(date('Y-m-d'));
                $booking->days_pending = (int) round(($endTs - $startTs) / 86400) + 1;
            }
        }

        return $this->corsJson($bookings, 200);
    }

    public function MailBookingError(Request $request)
    {
        $this->assertApiKey($request);

        $payload = $request->all();
        try {
            DB::table('virtualdesigns_rentalsunited_log')->insert([
                'request' => json_encode($payload),
                'response' => 'Booking error notification received',
                'response_id' => 500,
            ]);
        } catch (\Throwable $th) {
            return $this->corsJson($th->getMessage(), 500);
        }

        return $this->corsJson(['code' => 200, 'message' => 'Logged'], 200);
    }

    public function MakePayment(Request $request)
    {
        $this->assertApiKey($request);

        $siteName = $request->input('SiteName');
        $amount = (float) $request->input('Amount');
        $arrival = date('Y-m-d', strtotime((string) $request->input('Arrival')));
        $departure = date('Y-m-d', strtotime((string) $request->input('Departure')));
        $adults = (int) $request->input('Adults');
        $children = (int) $request->input('Children');
        $propertyRuId = $request->input('PropertyId');
        $customerName = $request->input('CustomerName');
        $customerSurname = $request->input('CustomerSurname');
        $customerEmail = $request->input('CustomerEmail');
        $customerPhoneNumber = $request->input('CustomerPhoneNumber');
        $successUrl = $request->input('SuccessUrl');
        $failUrl = $request->input('FailUrl');

        $prop = DB::table('virtualdesigns_properties_properties')->where('rentals_united_id', '=', $propertyRuId)->first();
        if (!$prop) {
            return $this->corsJson(['code' => 404, 'message' => 'Property not found'], 404);
        }

        $currencyRec = null;
        if ($prop->pricelabs_id !== null) {
            try {
                $priceListsTable = $this->getPriceListsTableName();
                $currencyRec = DB::table($priceListsTable)
                    ->where('pl_id', '=', $prop->pricelabs_id)
                    ->select('currency')
                    ->first();
            } catch (\Throwable $e) {
                $currencyRec = null;
            }
        }
        $currency = $currencyRec->currency ?? 'ZAR';

        $totalRands = $amount;
        $totalDollars = $amount;
        $totalEuros = $amount;
        $totalMur = $amount;

        if ($currency === 'EUR') {
            $totalRands = $amount * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'EUR/ZAR')->value('rate');
            $totalDollars = $amount * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'EUR/USD')->value('rate');
            $totalMur = $amount * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'EUR/MUR')->value('rate');
            $totalEuros = $amount;
        } elseif ($currency === 'USD') {
            $totalRands = $amount * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'USD/ZAR')->value('rate');
            $totalEuros = $amount * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'USD/EUR')->value('rate');
            $totalMur = $amount * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'USD/MUR')->value('rate');
            $totalDollars = $amount;
        } elseif ($currency === 'ZAR') {
            $totalDollars = $amount * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'ZAR/USD')->value('rate');
            $totalEuros = $amount * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'ZAR/EUR')->value('rate');
            $totalMur = $totalDollars * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'USD/MUR')->value('rate');
            $totalRands = $amount;
        }

        $bookingId = DB::table('virtualdesigns_erpbookings_erpbookings')->insertGetId([
            'property_id' => $prop->id,
            'arrival_date' => $arrival,
            'departure_date' => $departure,
            'client_name' => trim($customerName . ' ' . $customerSurname),
            'client_phone' => $customerPhoneNumber,
            'client_mobile' => $customerPhoneNumber,
            'client_email' => $customerEmail,
            'created_at' => now(),
            'status' => 1,
            'so_type' => 'booking',
            'channel' => 'Host Agents',
            'total_com' => $prop->comm_percent,
            'bhr_com' => $prop->comm_percent,
            'booking_amount' => $amount,
            'total_rands' => $totalRands,
            'total_euros' => $totalEuros,
            'total_dollars' => $totalDollars,
            'total_mur' => $totalMur,
            'no_guests' => $adults + $children,
            'adults' => $adults,
            'children' => $children,
            'website_from' => $siteName,
        ]);

        DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->update([
            'booking_ref' => $bookingId . 'HA',
        ]);

        $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->first();

        $lineMur = 0.0;
        if ($prop->booking_fee > 0) {
            if ($currency === 'EUR') {
                $lineMur = $prop->booking_fee * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'EUR/MUR')->value('rate');
            } elseif ($currency === 'USD') {
                $lineMur = $prop->booking_fee * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'USD/MUR')->value('rate');
            } elseif ($currency === 'ZAR') {
                $lineMur = $prop->booking_fee * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'ZAR/USD')->value('rate');
                $lineMur = $lineMur * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'USD/MUR')->value('rate');
            }
            DB::table('virtualdesigns_erpbookings_fees')->insert([
                'description' => 'Booking Fee',
                'arrival_date' => $arrival,
                'departure_date' => $departure,
                'quantity' => 1,
                'unit_price' => $prop->booking_fee,
                'price' => $prop->booking_fee,
                'mur_unit_price' => $lineMur,
                'mur_price' => $lineMur,
                'booking_id' => $bookingId,
            ]);
        }

        if ($prop->clean_fee > 0) {
            if ($currency === 'EUR') {
                $lineMur = $prop->clean_fee * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'EUR/MUR')->value('rate');
            } elseif ($currency === 'USD') {
                $lineMur = $prop->clean_fee * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'USD/MUR')->value('rate');
            } elseif ($currency === 'ZAR') {
                $lineMur = $prop->clean_fee * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'ZAR/USD')->value('rate');
                $lineMur = $lineMur * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'USD/MUR')->value('rate');
            }
            DB::table('virtualdesigns_erpbookings_fees')->insert([
                'description' => 'Departure Fee',
                'arrival_date' => $arrival,
                'departure_date' => $departure,
                'quantity' => 1,
                'unit_price' => $prop->clean_fee,
                'price' => $prop->clean_fee,
                'mur_unit_price' => $lineMur,
                'mur_price' => $lineMur,
                'booking_id' => $bookingId,
            ]);
        }

        $amountExFees = $amount - ($prop->booking_fee + $prop->clean_fee);
        if ($currency === 'EUR') {
            $lineMur = $amountExFees * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'EUR/MUR')->value('rate');
        } elseif ($currency === 'USD') {
            $lineMur = $amountExFees * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'USD/MUR')->value('rate');
        } elseif ($currency === 'ZAR') {
            $lineMur = $amountExFees * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'ZAR/USD')->value('rate');
            $lineMur = $lineMur * (float) DB::table('virtualdesigns_exchange_rates')->where('symbol', '=', 'USD/MUR')->value('rate');
        }

        $startTs = strtotime($arrival);
        $endTs = strtotime($departure);
        $nights = max(1, (int) round(($endTs - $startTs) / 86400));

        DB::table('virtualdesigns_erpbookings_fees')->insert([
            'description' => '[' . $prop->id . '] ' . $prop->accounting_name,
            'arrival_date' => $arrival,
            'departure_date' => $departure,
            'quantity' => $nights,
            'unit_price' => $amountExFees / $nights,
            'price' => $amountExFees,
            'mur_unit_price' => $lineMur / $nights,
            'mur_price' => $lineMur,
            'booking_id' => $bookingId,
        ]);

        $opInfo = DB::table('virtualdesigns_operationalinformation_operationalinformation')->where('property_id', '=', $prop->id)->first();
        $managerFees = DB::table('virtualdesigns_propertymanagerfees_propertymanagerfees')->where('property_id', '=', $prop->id)->first();
        $extras = DB::table('virtualdesigns_extracharges_extracharges')->where('property_id', '=', $prop->id)->first();

        $arrivalCleanPrice = 0.0;
        $arrivalConciergePrice = 0.0;
        $departureCleanPrice = 0.0;
        $departureConciergePrice = 0.0;
        if ($managerFees !== null) {
            if ($this->isHoliday($arrival)) {
                $arrivalCleanPrice = (float) $managerFees->arrival_clean * 1.5;
                $arrivalConciergePrice = (float) $managerFees->concierge_fee_arrival;
                $departureCleanPrice = (float) $managerFees->departure_clean * 1.5;
                $departureConciergePrice = (float) $managerFees->concierge_fee_departure * 1.5;
            } else {
                $arrivalCleanPrice = (float) $managerFees->arrival_clean;
                $arrivalConciergePrice = (float) $managerFees->concierge_fee_arrival;
                $departureCleanPrice = (float) $managerFees->departure_clean;
                $departureConciergePrice = (float) $managerFees->concierge_fee_departure;
            }
        }

        $welcomePackPrice = $managerFees ? (float) $managerFees->welcome_pack : 0.0;
        $msaPrice = $managerFees ? (float) $managerFees->mid_stay_clean : 0.0;

        DB::table('virtualdesigns_cleans_cleans')->insert([
            'property_id' => $prop->id,
            'booking_id' => $bookingId,
            'clean_type' => 'Arrival Clean',
            'clean_date' => $arrival,
            'supplier_id' => $prop->user_id,
            'price' => $arrivalCleanPrice,
            'status' => 1,
        ]);
        DB::table('virtualdesigns_cleans_cleans')->insert([
            'property_id' => $prop->id,
            'booking_id' => $bookingId,
            'clean_type' => 'Concierge Arrival',
            'clean_date' => $arrival,
            'supplier_id' => $prop->user_id,
            'price' => $arrivalConciergePrice,
            'status' => 1,
        ]);
        DB::table('virtualdesigns_cleans_cleans')->insert([
            'property_id' => $prop->id,
            'booking_id' => $bookingId,
            'clean_type' => 'Welcome Pack',
            'clean_date' => $arrival,
            'supplier_id' => $prop->user_id,
            'price' => $welcomePackPrice,
            'status' => 1,
        ]);

        if ($opInfo !== null && (int) $opInfo->linen_pool === 1 && (int) $opInfo->departure_linen === 0) {
            DB::table('virtualdesigns_laundry_laundry')->insert([
                'property_id' => $prop->id,
                'booking_id' => $bookingId,
                'supplier_id' => $opInfo->linen_supplier_id,
                'action_date' => $arrival,
                'price' => $extras ? (float) $extras->fanote_prices : 0.0,
                'stage' => 'Pending',
                'status' => 1,
            ]);
        }

        DB::table('virtualdesigns_cleans_cleans')->insert([
            'property_id' => $prop->id,
            'booking_id' => $bookingId,
            'clean_type' => 'Departure Clean',
            'clean_date' => $departure,
            'supplier_id' => $prop->user_id,
            'price' => $departureCleanPrice,
            'status' => 1,
        ]);
        DB::table('virtualdesigns_cleans_cleans')->insert([
            'property_id' => $prop->id,
            'booking_id' => $bookingId,
            'clean_type' => 'Concierge Departure',
            'clean_date' => $departure,
            'supplier_id' => $prop->user_id,
            'price' => $departureConciergePrice,
            'status' => 1,
        ]);

        if ($opInfo !== null && (int) $opInfo->linen_pool === 1 && (int) $opInfo->departure_linen === 1) {
            DB::table('virtualdesigns_laundry_laundry')->insert([
                'property_id' => $prop->id,
                'booking_id' => $bookingId,
                'supplier_id' => $opInfo->linen_supplier_id,
                'action_date' => $departure,
                'price' => $extras ? (float) $extras->fanote_prices : 0.0,
                'stage' => 'Pending',
                'status' => 1,
            ]);
        }

        $msaCount = 1;
        $msaDate = $booking->arrival_date;
        $hasFirst = false;
        while (strtotime($msaDate) < strtotime($booking->departure_date)) {
            $msaDate = date('Y-m-d', strtotime($msaDate . '+ 1 day'));
            if ($msaCount === 8) {
                if ($hasFirst) {
                    $taskDate = date('Y-m-d', strtotime($msaDate . '- 9 day'));
                } else {
                    $taskDate = date('Y-m-d', strtotime($msaDate . '- 5 day'));
                    $hasFirst = true;
                }
                DB::table('virtualdesigns_cleans_cleans')->insert([
                    'property_id' => $prop->id,
                    'booking_id' => $bookingId,
                    'clean_type' => 'MSA',
                    'clean_date' => $taskDate,
                    'supplier_id' => $prop->user_id,
                    'price' => $msaPrice,
                    'status' => 1,
                ]);
                if ($opInfo !== null && (int) $opInfo->linen_pool === 1) {
                    DB::table('virtualdesigns_laundry_laundry')->insert([
                        'property_id' => $prop->id,
                        'booking_id' => $bookingId,
                        'supplier_id' => $opInfo->linen_supplier_id,
                        'action_date' => $taskDate,
                        'price' => $extras ? (float) $extras->fanote_prices : 0.0,
                        'stage' => 'Pending',
                        'status' => 1,
                    ]);
                }
                $msaCount = 1;
            } else {
                $msaCount++;
            }
        }

        $baseUrl = env('TURNSTAY_BASE_URL', 'https://prod.turnstay.com/api/v1');
        $apiKey = env('TURNSTAY_API_KEY');
        $accountZa = env('TURNSTAY_ACCOUNT_ZA');
        $accountMu = env('TURNSTAY_ACCOUNT_MU');

        if (!$apiKey) {
            return $this->corsJson(['code' => 500, 'message' => 'TURNSTAY_API_KEY not configured'], 500);
        }

        $tenantResp = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
        ])->get($baseUrl . '/company');

        $tenantId = $tenantResp->json('tenant_id');
        if (!$tenantId) {
            return $this->corsJson(['code' => 500, 'message' => 'Unable to resolve TurnStay tenant'], 500);
        }

        if ($siteName === 'hostagents_mu') {
            $accountId = $accountMu;
            $billingCurrency = 'EUR';
        } else {
            $accountId = $accountZa;
            $billingCurrency = 'ZAR';
        }

        if (!$accountId) {
            return $this->corsJson(['code' => 500, 'message' => 'TurnStay account not configured'], 500);
        }

        $intentBody = [
            'account_id' => (int) $accountId,
            'billing_amount' => (int) round($amount * 100),
            'billing_currency' => $billingCurrency,
            'checkin_date' => $arrival,
            'checkout_date' => $departure,
            'description' => $nights . ' nights in ' . $prop->name,
            'product' => $prop->name,
            'customer' => trim($customerName . ' ' . $customerSurname),
            'customer_email' => $customerEmail,
            'customer_phone_number' => $customerPhoneNumber,
            'success_redirect_url' => $successUrl,
            'failed_redirect_url' => $failUrl,
            'payment_url_style' => 'string',
            'payment_type' => 'Card Payment',
            'merchant_reference' => $booking->booking_ref,
        ];

        $intentResp = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $apiKey,
            'X-Tenant-ID' => $tenantId,
        ])->post($baseUrl . '/payments/intent', $intentBody);

        if (!$intentResp->ok()) {
            return $this->corsJson(['code' => 500, 'message' => 'TurnStay request failed'], 500);
        }

        $paymentUrl = $intentResp->json('turnstay_payment_url');

        return $this->corsJson([
            'Code' => 200,
            'BookingId' => $bookingId,
            'BookingRef' => $booking->booking_ref,
            'PaymentUrl' => $paymentUrl,
        ], 200);
    }

    public function ConfirmPayment(Request $request)
    {
        $this->assertApiKey($request);

        $bookingId = $request->input('BookingId');
        if ($bookingId === null) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing BookingId'], 400);
        }

        DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->update([
            'status' => 0,
            'updated_at' => now(),
        ]);
        DB::table('virtualdesigns_cleans_cleans')->where('booking_id', '=', $bookingId)->update(['status' => 0]);
        DB::table('virtualdesigns_laundry_laundry')->where('booking_id', '=', $bookingId)->update(['status' => 0]);

        $booking = DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $bookingId)->first();
        if (!$booking) {
            return $this->corsJson(['code' => 404, 'message' => 'Booking not found'], 404);
        }

        $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $booking->property_id)->first();
        if ($prop && $prop->pricelabs_id !== null) {
            try {
                $priceListsTable = $this->getPriceListsTableName();
                DB::table($priceListsTable)
                    ->where('pl_id', '=', $prop->pricelabs_id)
                    ->where('date', '>=', $booking->arrival_date)
                    ->where('date', '<', $booking->departure_date)
                    ->update(['booked' => 1]);

                $bookedDates = DB::table($priceListsTable)
                    ->where('pl_id', '=', $prop->pricelabs_id)
                    ->where('date', '>=', $booking->arrival_date)
                    ->where('date', '<', $booking->departure_date)
                    ->get();
            } catch (\Throwable $e) {
                $bookedDates = collect([]);
            }

            $xmlAvail = '';
            foreach ($bookedDates as $bookedDate) {
                $xmlAvail .= "<Date From=\"{$bookedDate->date}\" To=\"{$bookedDate->date}\"><U>0</U><MS>{$bookedDate->min_stay}</MS><C>4</C></Date>";
            }

            if ($prop->rentals_united_id) {
                $xml = "<Push_PutAvbUnits_RQ><Authentication><UserName>book@hostagents.com</UserName><Password>TX@m@Yy6hSUs6N!</Password></Authentication><MuCalendar PropertyID=\"{$prop->rentals_united_id}\">";
                $xml .= $xmlAvail;
                $xml .= '</MuCalendar></Push_PutAvbUnits_RQ>';
                $this->rentalsUnitedRequest($xml);
            }
        }

        return $this->corsJson(['Code' => 200, 'Message' => 'Success'], 200);
    }

    public function GetStatements(Request $request, $userid)
    {
        $this->assertApiKey($request);

        $countryId = $request->header('countryid');
        if ($countryId !== null) {
            if ((int) $countryId === 847) {
                $propIdsZa = DB::connection('acclive')->table('owner_statements')->whereNull('DeletedAt')->pluck('PropertyId')->toArray();
                $propIdsMu = [];
                $propIdsUae = [];
            } elseif ((int) $countryId === 846) {
                $propIdsZa = [];
                $propIdsMu = DB::connection('acctest')->table('owner_statements')->whereNull('DeletedAt')->pluck('PropertyId')->toArray();
                $propIdsUae = [];
            } elseif ((int) $countryId === 854) {
                $propIdsZa = [];
                $propIdsMu = [];
                $propIdsUae = DB::connection('accuae')->table('owner_statements')->whereNull('DeletedAt')->pluck('PropertyId')->toArray();
            } else {
                $propIdsZa = [];
                $propIdsMu = [];
                $propIdsUae = [];
            }
        } else {
            $propIdsZa = DB::connection('acclive')->table('owner_statements')->whereNull('DeletedAt')->pluck('PropertyId')->toArray();
            $propIdsMu = DB::connection('acctest')->table('owner_statements')->whereNull('DeletedAt')->pluck('PropertyId')->toArray();
            $propIdsUae = DB::connection('accuae')->table('owner_statements')->whereNull('DeletedAt')->pluck('PropertyId')->toArray();
        }

        $propIds = array_values(array_unique(array_merge($propIdsZa, $propIdsMu, $propIdsUae)));
        $filterProperties = DB::table('virtualdesigns_properties_properties')->whereIn('id', $propIds)->get();
        $ownerIds = DB::table('virtualdesigns_properties_properties')->whereIn('id', $propIds)->pluck('owner_id')->toArray();
        $owners = DB::table('users')->whereIn('id', $ownerIds)->get();
        $portfolioManagerIds = DB::table('virtualdesigns_properties_properties')->whereIn('id', $propIds)->pluck('portfolio_manager_id')->toArray();
        $creditorIds = DB::table('virtualdesigns_properties_properties')->whereIn('id', $propIds)->pluck('creditor_id')->toArray();
        $portfolioManagers = DB::table('users')->whereIn('id', $portfolioManagerIds)->get();
        $creditors = DB::table('users')->whereIn('id', $creditorIds)->get();

        $propertyId = $request->header('propertyid');

        if ($request->header('month') !== null && $request->header('year') !== null) {
            $month = $request->header('month');
            $year = $request->header('year');
            $startDate = $year . '-' . $month . '-01';
            $endDate = date('Y-m-t', strtotime($startDate));
        } else {
            $startDate = date('2024-03-01');
            $endDate = date('Y-m-d', strtotime('last day of last month'));
        }

        if ($request->header('duedate') !== null) {
            $dueDate = date('Y-m-' . $request->header('duedate'), strtotime($startDate . ' + 1 month'));
        } else {
            $dueDate = null;
        }

        $groupId = DB::table('users_groups')->where('user_id', '=', $userid)->value('user_group_id');

        if ($propertyId !== null) {
            $propRec = DB::table('virtualdesigns_properties_properties')->where('id', '=', $propertyId)->first();
            if (!$propRec) {
                return $this->corsJson(['error' => 'Property not found'], 404);
            }

            if ($groupId == 1) {
                if ((int) $propRec->country_id === 846) {
                    $statements = DB::connection('acctest')->table('owner_statements')
                        ->where('PropertyId', '=', $propertyId)
                        ->whereBetween('StartDate', [$startDate, $endDate])
                        ->whereNull('DeletedAt')
                        ->whereNotNull('DateSent');
                } elseif ((int) $propRec->country_id === 854) {
                    $statements = DB::connection('accuae')->table('owner_statements')
                        ->where('PropertyId', '=', $propertyId)
                        ->whereBetween('StartDate', [$startDate, $endDate])
                        ->whereNull('DeletedAt')
                        ->whereNotNull('DateSent');
                } else {
                    $statements = DB::connection('acclive')->table('owner_statements')
                        ->where('PropertyId', '=', $propertyId)
                        ->whereBetween('StartDate', [$startDate, $endDate])
                        ->whereNull('DeletedAt')
                        ->whereNotNull('DateSent');
                }
            } else {
                if ((int) $propRec->country_id === 846) {
                    $recentRecords = DB::connection('acctest')->table('owner_statements')
                        ->whereBetween('StartDate', [$startDate, $endDate])
                        ->select('PropertyId', DB::raw('MAX(Id) as Id'))
                        ->groupBy('PropertyId')
                        ->get();
                    $statements = DB::connection('acctest')->table('owner_statements')
                        ->whereIn('Id', $recentRecords->pluck('Id'))
                        ->where('PropertyId', '=', $propertyId)
                        ->whereNull('DeletedAt');
                } elseif ((int) $propRec->country_id === 854) {
                    $recentRecords = DB::connection('accuae')->table('owner_statements')
                        ->whereBetween('StartDate', [$startDate, $endDate])
                        ->select('PropertyId', DB::raw('MAX(Id) as Id'))
                        ->groupBy('PropertyId')
                        ->get();
                    $statements = DB::connection('accuae')->table('owner_statements')
                        ->whereIn('Id', $recentRecords->pluck('Id'))
                        ->where('PropertyId', '=', $propertyId)
                        ->whereNull('DeletedAt');
                } else {
                    $recentRecords = DB::connection('acclive')->table('owner_statements')
                        ->whereBetween('StartDate', [$startDate, $endDate])
                        ->select('PropertyId', DB::raw('MAX(Id) as Id'))
                        ->groupBy('PropertyId')
                        ->get();
                    $statements = DB::connection('acclive')->table('owner_statements')
                        ->whereIn('Id', $recentRecords->pluck('Id'))
                        ->where('PropertyId', '=', $propertyId)
                        ->whereNull('DeletedAt');
                }
            }
        } else {
            if ($groupId == 1) {
                $ownerProps = DB::table('virtualdesigns_properties_properties')->where('owner_id', '=', $userid)->pluck('id')->toArray();
                $statementsZa = DB::connection('acclive')->table('owner_statements')
                    ->whereIn('PropertyId', $ownerProps)
                    ->whereNull('DeletedAt')
                    ->whereBetween('StartDate', [$startDate, $endDate])
                    ->whereNotNull('DateSent')
                    ->orderBy('CreatedAt', 'Asc');
                $statementsMu = DB::connection('acctest')->table('owner_statements')
                    ->whereIn('PropertyId', $ownerProps)
                    ->whereNull('DeletedAt')
                    ->whereBetween('StartDate', [$startDate, $endDate])
                    ->whereNotNull('DateSent')
                    ->orderBy('CreatedAt', 'Asc');
                $statementsUae = DB::connection('accuae')->table('owner_statements')
                    ->whereIn('PropertyId', $ownerProps)
                    ->whereNull('DeletedAt')
                    ->whereBetween('StartDate', [$startDate, $endDate])
                    ->whereNotNull('DateSent')
                    ->orderBy('CreatedAt', 'Asc');
            } else {
                $recentRecordsZa = DB::connection('acclive')->table('owner_statements')
                    ->whereBetween('StartDate', [$startDate, $endDate])
                    ->whereIn('PropertyId', $propIds)
                    ->select('PropertyId', DB::raw('MAX(Id) as Id'))
                    ->groupBy('PropertyId')
                    ->get();
                $recentRecordsMu = DB::connection('acctest')->table('owner_statements')
                    ->whereBetween('StartDate', [$startDate, $endDate])
                    ->whereIn('PropertyId', $propIds)
                    ->select('PropertyId', DB::raw('MAX(Id) as Id'))
                    ->groupBy('PropertyId')
                    ->get();
                $recentRecordsUae = DB::connection('accuae')->table('owner_statements')
                    ->whereBetween('StartDate', [$startDate, $endDate])
                    ->whereIn('PropertyId', $propIds)
                    ->select('PropertyId', DB::raw('MAX(Id) as Id'))
                    ->groupBy('PropertyId')
                    ->get();

                $statementsZa = DB::connection('acclive')->table('owner_statements')->whereIn('Id', $recentRecordsZa->pluck('Id'))->whereNull('DeletedAt');
                $statementsMu = DB::connection('acctest')->table('owner_statements')->whereIn('Id', $recentRecordsMu->pluck('Id'))->whereNull('DeletedAt');
                $statementsUae = DB::connection('accuae')->table('owner_statements')->whereIn('Id', $recentRecordsUae->pluck('Id'))->whereNull('DeletedAt');
            }
        }

        $response = [
            'statements_za' => isset($statementsZa) ? $statementsZa->get() : (isset($statements) ? $statements->get() : []),
            'statements_mu' => isset($statementsMu) ? $statementsMu->get() : [],
            'statements_uae' => isset($statementsUae) ? $statementsUae->get() : [],
            'filter_properties' => $filterProperties,
            'owners' => $owners,
            'portfolio_managers' => $portfolioManagers,
            'creditors' => $creditors,
            'due_date' => $dueDate,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];

        return $this->corsJson($response, 200);
    }

    public function SendStatements(Request $request, $id)
    {
        $this->assertApiKey($request);

        $propId = $request->header('propid');
        $statement = null;

        if ($propId !== null) {
            $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $propId)->first();
            if ($prop && (int) $prop->country_id === 846) {
                $statement = DB::connection('acctest')->table('owner_statements')->where('Id', '=', $id)->first();
                DB::connection('acctest')->table('owner_statements')->where('Id', '=', $id)->update(['DateSent' => now()]);
            } elseif ($prop && (int) $prop->country_id === 854) {
                $statement = DB::connection('accuae')->table('owner_statements')->where('Id', '=', $id)->first();
                DB::connection('accuae')->table('owner_statements')->where('Id', '=', $id)->update(['DateSent' => now()]);
            } else {
                $statement = DB::connection('acclive')->table('owner_statements')->where('Id', '=', $id)->first();
                DB::connection('acclive')->table('owner_statements')->where('Id', '=', $id)->update(['DateSent' => now()]);
            }
        } else {
            $statement = DB::connection('acclive')->table('owner_statements')->where('Id', '=', $id)->first();
            DB::connection('acclive')->table('owner_statements')->where('Id', '=', $id)->update(['DateSent' => now()]);
        }

        return $this->corsJson($statement ?? 'success', 200);
    }

    public function UpdateStatements(Request $request, $id)
    {
        $this->assertApiKey($request);

        $propId = $request->header('propid');
        $statement = null;
        $connection = 'acclive';

        if ($propId !== null) {
            $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $propId)->first();
            if ($prop && (int) $prop->country_id === 846) {
                $connection = 'acctest';
            } elseif ($prop && (int) $prop->country_id === 854) {
                $connection = 'accuae';
            }
        }

        $statement = DB::connection($connection)->table('owner_statements')->where('Id', '=', $id)->first();
        if (!$statement) {
            return $this->corsJson(['code' => 404, 'message' => 'Statement not found'], 404);
        }

        $ready = $request->header('ready') ?? $statement->Ready;
        $approved = $request->header('approved') ?? $statement->Approved;
        $paid = $request->header('paid') ?? $statement->Paid;
        $datePaid = $request->header('datepaid') ?? $statement->DatePaid;
        $notes = $request->header('notes') ?? $statement->Notes;

        DB::connection($connection)->table('owner_statements')->where('Id', '=', $id)->update([
            'Ready' => $ready,
            'Approved' => $approved,
            'Paid' => $paid,
            'DatePaid' => $datePaid,
            'Notes' => $notes,
        ]);

        return $this->corsJson('success', 200);
    }

    public function getCancelledQuotes(Request $request)
    {
        $start = $request->header('startdate') ? $request->header('startdate') . ' 00:00:01' : null;
        $end = $request->header('enddate') ? $request->header('enddate') . ' 23:59:59' : null;

        $bookings = DB::table('virtualdesigns_erpbookings_erpbookings as booking')
            ->leftJoin('virtualdesigns_properties_properties as property', 'booking.property_id', '=', 'property.id')
            ->where('booking.status', '=', 1)
            ->where('booking.quote_confirmed', '!=', 1);

        if ($start) {
            $bookings->where('booking.updated_at', '>=', $start);
        }
        if ($end) {
            $bookings->where('booking.updated_at', '<=', $end);
        }

        $results = $bookings->select('booking.*', 'property.name')->get()->unique();

        return $this->corsJson([$results, $start, $end], 200);
    }

    public function linkBooking(Request $request)
    {
        $this->assertApiKey($request);

        $bookingId = $request->header('bookingid');
        if ($bookingId === null) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing bookingid'], 400);
        }

        try {
            Http::timeout(15)->get('https://hostagents.co.za/' . $bookingId . '?mode=create');
        } catch (\Throwable $th) {
        }

        return $this->corsJson(['code' => 200, 'message' => 'Success'], 200);
    }

    private function isHoliday(string $date): bool
    {
        $dateStr = date('Y-m-d H:i:s', strtotime($date . ' 00:00:00'));
        $count = DB::table('virtualdesigns_publicholidays_publicholidays')
            ->where('date', '=', $dateStr)
            ->count();

        return $count > 0;
    }

    private function sendWelcomeLetter($booking): void
    {
        // Email templates are not part of this API-only backend.
    }

    private function pushNotify(int $userId, int $recId, string $type): void
    {
        try {
            $tokens = DB::table('react_user_tokens')->where('user_id', '=', $userId)->pluck('user_token');
            foreach ($tokens as $token) {
                Http::withHeaders(['Content-Type' => 'application/json'])
                    ->post('https://exp.host/--/api/v2/push/send', [
                        'to' => $token,
                        'title' => 'New ' . $type,
                        'body' => 'New ' . $type . ' created',
                        'content-available' => 1,
                        'data' => ['id' => $recId, 'type' => $type],
                    ]);
            }
        } catch (\Throwable $th) {
        }
    }

    private function insertCleanTask(int $propertyId, int $bookingId, string $type, string $date, int $supplierId, float $price): void
    {
        DB::table('virtualdesigns_cleans_cleans')->insert([
            'property_id' => $propertyId,
            'booking_id' => $bookingId,
            'clean_type' => $type,
            'clean_date' => $date,
            'supplier_id' => $supplierId,
            'price' => $price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertLaundryTask(int $propertyId, int $bookingId, int $supplierId, string $date, float $price): void
    {
        DB::table('virtualdesigns_laundry_laundry')->insert([
            'property_id' => $propertyId,
            'booking_id' => $bookingId,
            'supplier_id' => $supplierId,
            'action_date' => $date,
            'price' => $price,
            'stage' => 'Pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function nightsbridgePost(string $url, array $payload)
    {
        $response = Http::timeout(30)
            ->withHeaders([
                'Content-type' => 'application/json',
                'Accept' => 'application/json',
                'Cache-Control' => 'no-cache',
                'Authorization' => 'Basic NjMzOmh1dHJzZQ==',
            ])
            ->post($url, $payload);

        $body = $response->body();
        return $body !== '' ? json_decode($body) : null;
    }

    private function rentalsUnitedRequest(string $xml)
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'text/xml'])
                ->withBody($xml, 'text/xml')
                ->post('https://rm.rentalsunited.com/api/Handler.ashx');

            $xmlObject = new \SimpleXMLElement($response->body());
            $responseId = $xmlObject->attributes()->ID ?? $xmlObject->ResponseID;

            DB::table('virtualdesigns_rentalsunited_log')->insert([
                'request' => $xml,
                'response' => $response->body(),
                'response_id' => (string) $responseId,
            ]);

            return (object) json_decode(json_encode($xmlObject), true);
        } catch (\Throwable $th) {
            DB::table('virtualdesigns_rentalsunited_log')->insert([
                'request' => $xml,
                'response' => $th->getMessage(),
                'response_id' => 0,
            ]);

            return null;
        }
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

    private function notImplemented()
    {
        return $this->corsJson(['error' => 'Not implemented'], 501);
    }
}
