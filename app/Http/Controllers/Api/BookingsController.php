<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BookingsController extends Controller
{
    public function index(Request $request)
    {
        return $this->notImplemented();
    }

    public function show(Request $request, $id)
    {
        return $this->notImplemented();
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

            $availRows = DB::connection('remote')->table('price_lists')
                ->where('pl_id', '=', $prop->pricelabs_id)
                ->where('date', '>=', $arrival)
                ->where('date', '<=', $departure)
                ->get();

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
                $currency = DB::connection('remote')->table('price_lists')
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
        return $this->notImplemented();
    }

    public function nightsbridgeBookingsAll(Request $request)
    {
        return $this->notImplemented();
    }

    public function nightsbridgeBookingsList(Request $request)
    {
        return $this->notImplemented();
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
                $availRows = DB::connection('remote')->table('price_lists')
                    ->where('pl_id', '=', $prop->pricelabs_id)
                    ->where('date', '>=', $body->arrival)
                    ->where('date', '<', $body->departure)
                    ->get();
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

            DB::connection('remote')->table('price_lists')
                ->where('pl_id', '=', $prop->pricelabs_id)
                ->where('date', '>=', $body->arrival)
                ->where('date', '<', $body->departure)
                ->update(['booked' => 1]);

            $bookedDates = DB::connection('remote')->table('price_lists')
                ->where('pl_id', '=', $prop->pricelabs_id)
                ->where('date', '>=', $body->arrival)
                ->where('date', '<', $body->departure)
                ->get();

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
                            $currency = DB::connection('remote')->table('price_lists')
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
            DB::connection('remote')->table('price_lists')
                ->where('date', '>=', $booking->arrival_date)
                ->where('date', '<', $booking->departure_date)
                ->where('pl_id', $prop->pricelabs_id)
                ->update(['booked' => 0]);
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
        return $this->notImplemented();
    }

    public function UpdateGuestDetails(Request $request)
    {
        return $this->notImplemented();
    }

    public function getBillingData(Request $request, $bookingId)
    {
        return $this->notImplemented();
    }

    public function getMails(Request $request, $bookingId)
    {
        return $this->notImplemented();
    }

    public function sendMail(Request $request, $id)
    {
        return $this->notImplemented();
    }

    public function getReflist(Request $request, $bookingRef)
    {
        return $this->notImplemented();
    }

    public function requestChanges(Request $request)
    {
        return $this->notImplemented();
    }

    public function confirmChanges(Request $request)
    {
        return $this->notImplemented();
    }

    public function getChanges(Request $request)
    {
        return $this->notImplemented();
    }

    public function RaiseSO(Request $request, $id)
    {
        return $this->notImplemented();
    }

    public function linkSo(Request $request, $id)
    {
        return $this->notImplemented();
    }

    public function NightsbridgeUpdate(Request $request, $id)
    {
        return $this->notImplemented();
    }

    public function allbookings(Request $request)
    {
        return $this->notImplemented();
    }

    public function MailBookingError(Request $request)
    {
        return $this->notImplemented();
    }

    public function MakePayment(Request $request)
    {
        return $this->notImplemented();
    }

    public function ConfirmPayment(Request $request)
    {
        return $this->notImplemented();
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
        return $this->notImplemented();
    }

    public function UpdateStatements(Request $request, $id)
    {
        return $this->notImplemented();
    }

    public function getCancelledQuotes(Request $request)
    {
        return $this->notImplemented();
    }

    public function linkBooking(Request $request)
    {
        return $this->notImplemented();
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
