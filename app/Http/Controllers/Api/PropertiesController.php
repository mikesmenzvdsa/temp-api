<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\RentalsUnitedService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PropertiesController extends Controller
{

    public function index(Request $request)
    {
        try {
            $this->assertApiKey($request);

            $propertyRecs = DB::table('virtualdesigns_properties_properties as properties')
                ->where('properties.is_live', '=', 1)
                ->whereNull('properties.deleted_at')
                ->leftJoin('users as owner', 'properties.owner_id', '=', 'owner.id')
                ->leftJoin('virtualdesigns_clientinformation_ as owner_info', 'properties.owner_id', '=', 'owner_info.user_id')
                ->leftJoin('virtualdesigns_extracharges_extracharges as fees', 'properties.id', '=', 'fees.property_id')
                ->leftJoin('virtualdesigns_locations_locations as suburb', 'properties.suburb_id', '=', 'suburb.id')
                ->leftJoin('virtualdesigns_locations_locations as city', 'properties.city_id', '=', 'city.id')
                ->select(
                    'properties.*',
                    'owner.name as owner_name',
                    'owner.surname as owner_surname',
                    'owner.email as owner_email',
                    'owner_info.contact_number as owner_phone',
                    'fees.departure_clean',
                    'fees.fanote_prices',
                    'suburb.name as suburb_name',
                    'city.name as city_name',
                    'owner_info.dstv_owner_sign_up',
                    'owner_info.dstv_date_signed_up',
                    'owner_info.dstv_future_cancel_date',
                    'owner_info.dstv_canceled',
                    'owner_info.dstv_date_canceled',
                    'owner_info.dstv_notes',
                    'owner_info.wifi_owner_signup',
                    'owner_info.wifi_date_signed_up',
                    'owner_info.wifi_future_cancel_date',
                    'owner_info.wifi_canceled',
                    'owner_info.wifi_date_canceled',
                    'owner_info.wifi_notes',
                    'owner_info.nightsbridge_future_cancel_date',
                    'owner_info.nightsbridge_canceled',
                    'owner_info.nightsbridge_date_canceled',
                    'owner_info.nightsbridge_notes',
                    'owner_info.airagents_future_cancel_date',
                    'owner_info.airagents_canceled',
                    'owner_info.airagents_date_canceled',
                    'owner_info.airagents_notes'
                )
                ->get()
                ->unique();

            return $this->corsJson($propertyRecs, 200);
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }
            return $this->corsJson(['error' => $e->getMessage()], 500);
        }
    }

    public function show(Request $request, $userid)
    {
        try {
            $this->assertApiKey($request);

            $limited = $request->header('limited') !== null;
            $country = $request->header('country');

            $groupId = DB::table('users_groups')->where('user_id', '=', $userid)->value('user_group_id');
            if ($groupId === null) {
                $groupId = 2;
            }

            $query = DB::table('virtualdesigns_properties_properties as properties')
                ->whereNull('properties.deleted_at')
                ->leftJoin('users as owner', 'properties.owner_id', '=', 'owner.id')
                ->leftJoin('virtualdesigns_clientinformation_ as owner_info', 'properties.owner_id', '=', 'owner_info.user_id')
                ->leftJoin('virtualdesigns_locations_locations as suburb', 'properties.suburb_id', '=', 'suburb.id')
                ->leftJoin('virtualdesigns_locations_locations as city', 'properties.city_id', '=', 'city.id')
                ->leftJoin('virtualdesigns_extracharges_extracharges as fees', 'properties.id', '=', 'fees.property_id');

            if ($groupId === 1) {
                $query->where('properties.owner_id', '=', $userid);
            }

            if ($groupId === 2 || $groupId === 6) {
                if ($userid == 1636 || $userid == 1709) {
                    $query->where('properties.name', 'like', '%Winelands Golf Lodges%');
                }
                if ($country !== null) {
                    if ($country === 'MU') {
                        $query->where('properties.country_id', '=', 846);
                    } else {
                        $query->where('properties.country_id', '!=', 846);
                    }
                }
            }

            if ($groupId === 3) {
                $query->where('properties.user_id', '=', $userid);
            }

            if ($groupId === 4) {
                $query
                    ->leftJoin('virtualdesigns_operationalinformation_operationalinformation as opinfo', 'properties.id', '=', 'opinfo.property_id')
                    ->where('opinfo.linen_supplier_id', '=', $userid);
            }

            if ($groupId === 5) {
                $query->where('properties.bodycorp_id', '=', $userid);
            }

            if ($limited) {
                $query->select('properties.id as id', 'properties.name as name');
            } else {
                $query->select(
                    'properties.*',
                    'owner.name as owner_name',
                    'owner.surname as owner_surname',
                    'owner.email as owner_email',
                    'owner_info.contact_number as owner_phone',
                    'suburb.name as suburb_name',
                    'city.name as city_name',
                    'fees.departure_clean',
                    'fees.fanote_prices',
                    'owner_info.dstv_owner_sign_up',
                    'owner_info.dstv_date_signed_up',
                    'owner_info.dstv_future_cancel_date',
                    'owner_info.dstv_canceled',
                    'owner_info.dstv_date_canceled',
                    'owner_info.dstv_notes',
                    'owner_info.wifi_owner_signup',
                    'owner_info.wifi_date_signed_up',
                    'owner_info.wifi_future_cancel_date',
                    'owner_info.wifi_canceled',
                    'owner_info.wifi_date_canceled',
                    'owner_info.wifi_notes',
                    'owner_info.nightsbridge_future_cancel_date',
                    'owner_info.nightsbridge_canceled',
                    'owner_info.nightsbridge_date_canceled',
                    'owner_info.nightsbridge_notes',
                    'owner_info.airagents_future_cancel_date',
                    'owner_info.airagents_canceled',
                    'owner_info.airagents_date_canceled',
                    'owner_info.airagents_notes'
                );
            }

            $propertyRecs = $query->get()->unique();

            return $this->corsJson($propertyRecs, 200);
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }
            return $this->corsJson(['error' => $e->getMessage()], 500);
        }
    }

    public function getSingleProperty(Request $request, $id)
    {
        if ($request->header('ra') !== null) {
            $property = DB::table('virtualdesigns_properties_properties')
                ->where('rentals_united_id', '=', $id)
                ->first();

            return $this->corsJson($property, 200);
        }

        $property = DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->first();
        if (!$property) {
            return $this->corsJson(['error' => 'Property not found'], 404);
        }

        $propertyArray = (array) $property;

        if ($property->owner_id !== null) {
            $ownerUser = DB::table('users')->where('id', '=', $property->owner_id)->first();
            $ownerInfo = DB::table('virtualdesigns_clientinformation_')
                ->where('user_id', '=', $property->owner_id)
                ->orderByDesc('id')
                ->first();

            if ($ownerInfo === null && $ownerUser !== null) {
                $ownerInfoId = DB::table('virtualdesigns_clientinformation_')->insertGetId([
                    'user_id' => $property->owner_id,
                    'client_name' => trim(($ownerUser->name ?? '') . ' ' . ($ownerUser->surname ?? '')),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $ownerInfo = DB::table('virtualdesigns_clientinformation_')->where('id', '=', $ownerInfoId)->first();
            }

            $propertyArray['owner_info'] = $ownerInfo;
        }

        $country = DB::table('virtualdesigns_locations_locations')->where('id', '=', $property->country_id)->first();
        $province = DB::table('virtualdesigns_locations_locations')->where('id', '=', $property->province_id)->first();
        $city = DB::table('virtualdesigns_locations_locations')->where('id', '=', $property->city_id)->first();
        $suburb = null;

        if ($property->suburb_id !== null) {
            $suburb = DB::table('virtualdesigns_locations_locations')->where('id', '=', $property->suburb_id)->first();
        }

        $checkedLocations = DB::table('virtualdesigns_locations_locations_properties')
            ->where('property_id', '=', $property->id)
            ->pluck('location_id');

        $extras = [
            'country' => $country,
            'countries' => DB::table('virtualdesigns_locations_locations')->where('location_type', '=', 'Country')->get(),
            'province' => $province,
            'provinces' => DB::table('virtualdesigns_locations_locations')->where('location_type', '=', 'Province')->get(),
            'city' => $city,
            'cities' => DB::table('virtualdesigns_locations_locations')->where('location_type', '=', 'City')->get(),
            'suburb' => $suburb,
            'suburbs' => DB::table('virtualdesigns_locations_locations')->where('location_type', '=', 'Suburb')->get(),
            'regions' => DB::table('virtualdesigns_locations_locations')
                ->where('parent_id', '=', $property->province_id)
                ->where('location_type', '=', 'Region')
                ->get(),
            'sub_suburbs' => [],
            'checked_locations' => $checkedLocations,
            'listing_types' => DB::table('virtualdesigns_properties_listing_types')->where('is_room_type', '=', 0)->get(),
            'rate_basis' => DB::table('virtualdesigns_properties_rate_bases')->get(),
            'users' => DB::table('users')->get(),
            'user' => DB::table('users')->where('id', '=', $property->user_id)->first(),
            'property_manager_fees' => DB::table('virtualdesigns_propertymanagerfees_propertymanagerfees')
                ->where('property_id', '=', $property->id)
                ->first(),
            'extra_fees' => DB::table('virtualdesigns_extracharges_extracharges')
                ->where('property_id', '=', $property->id)
                ->first(),
            'op_info' => DB::table('virtualdesigns_operationalinformation_operationalinformation')
                ->where('property_id', '=', $property->id)
                ->first(),
            'body_corporate_users' => DB::table('virtualdesigns_bodycorp_bodycorp')->whereNull('deleted_at')->get(),
        ];

        return $this->corsJson([$propertyArray, $extras], 200);
    }

    public function store(Request $request)
    {
        $data = $request->all();
        if (empty($data['name'])) {
            return $this->corsJson(['errors' => ['name' => ['The name field is required.']]], 401);
        }

        try {
            $slug = Str::slug($data['name']);
            $data['booknow_url'] = "https://hostagents.co.za/accommodation/{$slug}";
            $data['slug'] = $slug;

            $nextId = (int) DB::table('virtualdesigns_properties_properties')->max('id') + 1;
            $createData = $this->filterPropertyFields($data);

            foreach ($createData as $field => $value) {
                if ($value !== null && $value !== '') {
                    $this->logChange($data['change_user'] ?? null, $nextId, $field, '', $value);
                }
            }

            $createData['created_at'] = date('Y-m-d H:i:s');
            $createData['updated_at'] = date('Y-m-d H:i:s');

            $propertyId = DB::table('virtualdesigns_properties_properties')->insertGetId($createData);

            DB::table('virtualdesigns_extracharges_extracharges')->insert([
                'property_id' => $propertyId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $property = DB::table('virtualdesigns_properties_properties')->where('id', '=', $propertyId)->first();
            DB::table('virtualdesigns_properties_properties')->where('id', '=', $propertyId)->update([
                'accounting_name' => $property->name,
            ]);

            $response = ['establishment' => $property];

            return $this->corsJson($response, 200);
        } catch (\Throwable $th) {
            return $this->corsJson(['error' => $th->getMessage()], 200);
        }
    }

    public function uploadpdf(Request $request, $id)
    {
        return $this->corsJson(['error' => 'Not implemented'], 501);
    }

    public function update(Request $request, $id)
    {
        $property = DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->first();
        if (!$property) {
            return $this->corsJson(['error' => 'Property not found'], 404);
        }

        try {
            $payload = $request->all();
            $today = date('Y-m-d');

            if (array_key_exists('user_id', $payload) && $payload['user_id'] != $property->user_id) {
                $tasks = DB::table('virtualdesigns_cleans_cleans')
                    ->where('property_id', '=', $property->id)
                    ->where('clean_date', '>', $today)
                    ->get();

                foreach ($tasks as $task) {
                    $supplierId = $payload['user_id'];
                    if ($property->concierge_id !== null) {
                        if (strtolower((string) $task->clean_type) === 'concierge arrival' || strtolower((string) $task->clean_type) === 'concierge departure') {
                            $supplierId = $property->concierge_id;
                        }
                    }

                    DB::table('virtualdesigns_cleans_cleans')->where('id', '=', $task->id)->update([
                        'supplier_id' => $supplierId,
                    ]);
                }
            }

            if (array_key_exists('concierge_id', $payload) && $payload['concierge_id'] != $property->concierge_id) {
                $tasks = DB::table('virtualdesigns_cleans_cleans')
                    ->where('property_id', '=', $property->id)
                    ->where('clean_date', '>', $today)
                    ->get();

                foreach ($tasks as $task) {
                    if (strtolower((string) $task->clean_type) === 'concierge arrival' || strtolower((string) $task->clean_type) === 'concierge departure') {
                        $supplierId = $payload['concierge_id'] ?? $property->user_id;
                        DB::table('virtualdesigns_cleans_cleans')->where('id', '=', $task->id)->update([
                            'supplier_id' => $supplierId,
                        ]);
                    }
                }
            }

            if (array_key_exists('regions', $payload)) {
                $this->updatePropertyLocations($payload['regions'], $id);
            }

            $updates = [];
            $changeUser = $payload['change_user'] ?? null;
            foreach ($this->propertyUpdateFields() as $field) {
                if (!array_key_exists($field, $payload)) {
                    continue;
                }

                $newValue = $payload[$field];
                $oldValue = $property->$field ?? null;

                if ($oldValue != $newValue) {
                    $this->logChange($changeUser, $property->id, $field, $oldValue, $newValue);
                }

                $updates[$field] = $newValue;
            }

            if (!empty($updates)) {
                $updates['updated_at'] = date('Y-m-d H:i:s');
                DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->update($updates);
            }

            $property = DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->first();
            $slug = Str::slug($property->name ?? '');
            DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->update([
                'booknow_url' => 'https://hostagents.co.za/accommodation/' . $slug,
                'slug' => $slug,
            ]);

            if ((int) $property->comm_update_required === 1) {
                $bookings = DB::table('virtualdesigns_erpbookings_erpbookings')
                    ->where('property_id', '=', $property->id)
                    ->where('arrival_date', '>', $today)
                    ->get();

                foreach ($bookings as $booking) {
                    $bhrCom = (int) $booking->is_thirdparty === 1 ? $property->comm_percent : $property->direct_comm;
                    $totalCom = ($booking->third_party_com ?? 0) + ($property->comm_percent ?? 0);

                    DB::table('virtualdesigns_erpbookings_erpbookings')->where('id', '=', $booking->id)->update([
                        'bhr_com' => $bhrCom,
                        'total_com' => $totalCom,
                    ]);
                }
            }

            return $this->corsJson(['success' => $property], 200);
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }
            return $this->corsJson(['error' => $e->getMessage()], 500);
        }
    }

    public function getTypes()
    {
        $types = DB::table('rentalsunited_property_types')->get();
        return $this->corsJson($types, 200);
    }

    public function getConfiguration(Request $request, $id)
    {
        $types = DB::table('virtualdesigns_properties_listing_types')
            ->whereNotNull('rentals_united_id')
            ->where('created_at', '=', '2023-07-24 11:40:23')
            ->get();

        $configSettings = DB::table('virtualdesigns_properties_configurations')
            ->where('property_id', '=', $id)
            ->get()
            ->groupBy('composition_name');

        $features = DB::table('virtualdesigns_features_features')
            ->where('is_room_type', '=', 1)
            ->whereNotNull('rentals_united_id')
            ->get()
            ->groupBy('rentals_united_composition_id');

        $response = [
            'types' => $types,
            'features' => $features,
            'configuration' => $configSettings,
        ];

        return $this->corsJson($response, 200);
    }

    public function updateConfiguration(Request $request, $id)
    {
        $configSettings = $request->input('config', []);

        DB::table('virtualdesigns_properties_configurations')->where('property_id', '=', $id)->delete();

        foreach ($configSettings as $configSetting) {
            $compositionId = null;
            foreach ($configSetting as $key => $value) {
                if ($key === 'rentals_united_composition_id') {
                    $compositionId = $value;
                    continue;
                }

                $compositionName = $key;
                foreach ($value as $feature) {
                    DB::table('virtualdesigns_properties_configurations')->insert([
                        'property_id' => $id,
                        'rentals_united_composition_id' => $compositionId,
                        'rentals_united_feature_id' => $feature['rentals_united_feature_id'] ?? null,
                        'composition_name' => $compositionName,
                    ]);
                }
            }
        }

        return $this->corsJson('Put', 200);
    }

    public function updateOwnerDetails(Request $request, $id)
    {
        $propertyColumns = Schema::getColumnListing('virtualdesigns_properties_properties');
        $payload = $request->all();

        $propertyUpdate = array_intersect_key($payload, array_flip($propertyColumns));
        if (!empty($propertyUpdate)) {
            DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->update($propertyUpdate);
        }

        $ownerInfo = DB::table('virtualdesigns_clientinformation_')->where('property_id', '=', $id)->first();
        if (array_key_exists('owner_id', $payload)) {
            $payload['user_id'] = $payload['owner_id'];
            unset($payload['owner_id']);
        }

        if ($ownerInfo !== null) {
            DB::table('virtualdesigns_clientinformation_')->where('id', '=', $ownerInfo->id)->update($payload);
        } else {
            $payload['property_id'] = $id;
            DB::table('virtualdesigns_clientinformation_')->insert($payload);
        }

        return $this->corsJson(['success' => true], 200)->header('Access-Control-Allow-Methods', 'PUT');
    }

    public function uploadOwnerDocuments(Request $request, $id)
    {
        return $this->corsJson(['error' => 'Not implemented'], 501);
    }

    public function deleteOwnerDocuments($documentId)
    {
        return $this->corsJson(['error' => 'Not implemented'], 501);
    }

    public function destroy($id)
    {
        return $this->corsJson(['error' => 'Not implemented'], 501);
    }

    public function getImages(Request $request, $id = null)
    {
        return $this->corsJson(['error' => 'Not implemented'], 501);
    }

    public function serp(Request $request)
    {
        try {
            $this->assertApiKey($request);

            $checkin = $request->header('arrival');
            $checkout = $request->header('departure');
            $mapLat = $request->header('lat');
            $mapLong = $request->header('long');
            $locId = $request->header('locid');
            $page = (int) ($request->header('page') ?? 1);
            $distance = $request->header('distance');

            $away = 20;
            if ($distance !== null) {
                $distance = (int) $distance;
                if ($distance === 12) {
                    $away = 20;
                } elseif ($distance < 12) {
                    $dif = 12 - $distance;
                    $away = (int) (pow(2, $dif) * 20);
                } else {
                    $dif = 12 - $distance;
                    $away = $dif === -1 ? 10 : (int) (pow(2, $dif) * 20);
                }
            }

            $loc = null;
            if ($locId !== null) {
                $loc = DB::table('virtualdesigns_locations_locations')->where('id', '=', $locId)->first();
            }

            $propIds = [];
            if (($mapLat !== null && $mapLong !== null) || ($loc !== null)) {
                $gpsProps = DB::table('virtualdesigns_properties_properties')
                    ->where('is_live', '=', 1)
                    ->whereNotNull('nb_id')
                    ->where('nb_id', '!=', '')
                    ->select('id', 'latitude', 'longitude')
                    ->get();

                foreach ($gpsProps as $gpsProp) {
                    $distanceValue = $this->haversineGreatCircleDistance(
                        $mapLat ?? $loc->latitude,
                        $mapLong ?? $loc->longitude,
                        $gpsProp->latitude,
                        $gpsProp->longitude
                    );

                    if (round($distanceValue, 1) <= $away) {
                        $propIds[] = $gpsProp->id;
                    }
                }
            }

            $query = DB::table('virtualdesigns_properties_properties')
                ->where('is_live', '=', 1)
                ->whereNotNull('nb_id')
                ->where('nb_id', '!=', '');

            if (!empty($propIds)) {
                $query->whereIn('id', $propIds);
            }

            if ($request->header('actype') !== null) {
                $acTypes = json_decode($request->header('actype'), true) ?? [];
                if (!empty($acTypes)) {
                    $query->whereIn('listing_type_id', $acTypes);
                }
            }

            if ($request->header('bedrooms') !== null) {
                $bedrooms = json_decode($request->header('bedrooms'), true) ?? [];
                if (!empty($bedrooms)) {
                    if (in_array(5, $bedrooms, true)) {
                        $query->where(function ($sub) use ($bedrooms) {
                            $sub->whereIn('bedroom_num', $bedrooms)
                                ->orWhere('bedroom_num', '>=', 5);
                        });
                    } else {
                        $query->whereIn('bedroom_num', $bedrooms);
                    }
                }
            }

            if ($request->header('features') !== null) {
                $features = json_decode($request->header('features'), true) ?? [];
                foreach ($features as $featureId) {
                    $query->whereIn('id', function ($sub) use ($featureId) {
                        $sub->select('property_id')
                            ->from('virtualdesigns_features_features_properties')
                            ->where('feature_id', '=', $featureId);
                    });
                }
            }

            $properties = $query->select('id', 'name', 'suburb_name', 'latitude', 'longitude', 'bedroom_num', 'bathroom_num', 'nb_id')->get();

            $finalArray = [];
            $ratesArray = [];
            if ($properties->count() > 0) {
                if ($checkin !== null && $checkout !== null) {
                    $propBbids = DB::table('virtualdesigns_properties_properties')
                        ->where('is_live', '=', 1)
                        ->whereNotNull('nb_id')
                        ->where('nb_id', '!=', '')
                        ->pluck('nb_id')
                        ->toArray();

                    $propBbidsChunked = array_chunk($propBbids, 100);
                    $availArray = [];
                    $first = true;

                    foreach ($propBbidsChunked as $chunk) {
                        $arrayMessage = [
                            'messagename' => 'AvailRQ',
                            'credentials' => [
                                'nbid' => 633,
                                'password' => 'hutrse',
                            ],
                            'bblist' => ['bbid' => $chunk],
                            'startdate' => date('Y-m-d', strtotime($checkin)),
                            'enddate' => date('Y-m-d', strtotime($checkout)),
                            'showrates' => true,
                        ];

                        $ch = curl_init('https://www.nightsbridge.co.za/bridge/jsonapi/4.0');
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_HTTPHEADER => ['Content-type: application/json'],
                            CURLOPT_POSTFIELDS => json_encode($arrayMessage),
                        ]);

                        $response = curl_exec($ch);
                        curl_close($ch);

                        $responseArray = json_decode($response);
                        if (isset($responseArray->data->bb)) {
                            if ($first) {
                                $availArray = $responseArray->data->bb;
                                $first = false;
                            } else {
                                $availArray = array_merge($availArray, $responseArray->data->bb);
                            }
                        }
                    }

                    foreach ($properties as $property) {
                        $propKey = array_search($property->nb_id, array_column($availArray, 'bbid'));
                        if ($propKey === false) {
                            continue;
                        }

                        $availProp = $availArray[$propKey];
                        if (!isset($availProp->roomtypes)) {
                            continue;
                        }

                        $rate = current($availProp->roomtypes[0]->mealplans[0]->rates ?? []);
                        if ($rate === false) {
                            continue;
                        }

                        if ($request->header('range') !== null) {
                            $priceRange = explode(':', $request->header('range'));
                            if ($rate < $priceRange[0] || $rate > $priceRange[1]) {
                                continue;
                            }
                        }

                        $property->min_rate = $rate;
                        $finalArray[] = $property;
                        $ratesArray[] = $rate;
                    }

                    $sort = $request->header('sort');
                    if ($sort === 'ASC') {
                        array_multisort(array_column($finalArray, 'min_rate'), SORT_ASC, $finalArray);
                    }
                    if ($sort === 'DESC') {
                        array_multisort(array_column($finalArray, 'min_rate'), SORT_DESC, $finalArray);
                    }

                    if (!empty($ratesArray) && !empty($finalArray)) {
                        $finalArray[0]->slider_low = min($ratesArray);
                        $finalArray[0]->slider_high = max($ratesArray);
                    } elseif (!empty($finalArray)) {
                        $finalArray[0]->slider_low = 0;
                        $finalArray[0]->slider_high = 0;
                    }
                } else {
                    $finalArray = $properties->all();
                }

                if (!empty($finalArray)) {
                    $finalArray = new LengthAwarePaginator(
                        $this->getPaginatorSlice($finalArray, $page),
                        count($finalArray),
                        20,
                        $page
                    );

                    return $this->corsJson($finalArray, 200);
                }

                return $this->corsJson('No properties available', 404);
            }

            return $this->corsJson('No properties available', 404);
        } catch (\Throwable $e) {
            return $this->corsJson(['error' => $e->getMessage()], 500);
        }
    }

    public function onClickLive(Request $request, $id)
    {
        $today = date('Y-m-d');
        $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->first();
        if (!$prop) {
            return $this->corsJson(['error' => 'Property not found'], 404);
        }

        if ((int) $request->input('is_live') === 1) {
            $bookings = DB::table('virtualdesigns_erpbookings_erpbookings')
                ->where('quote_confirmed', '=', 1)
                ->where('status', '!=', 1)
                ->whereNull('deleted_at')
                ->where('so_type', '!=', 'block')
                ->where('property_id', '=', $prop->id)
                ->count();

            $fees = DB::table('virtualdesigns_extracharges_extracharges')
                ->where('property_id', '=', $prop->id)
                ->whereNull('deleted_at')
                ->orderByDesc('id')
                ->first();

            $hasFees = 0;
            if ($fees) {
                $hasFees = ($fees->monthly_management_fee > 0 || $fees->wifi_costs > 0 || $fees->netflix_costs > 0 || $fees->dstv_costs > 0) ? 1 : 0;
            }

            if ($bookings >= 1 || $hasFees === 1) {
                $message = 'Please ensure all bookings are cancelled and monthly fees such as WiFi, etc have been removed before deactivating the listing';
                return $this->corsJson($message, 400)->header('Access-Control-Allow-Methods', '*');
            }

            DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->update([
                'is_live' => 0,
                'date_deactivated' => $today,
            ]);
        }

        if ((int) $request->input('is_live') === 0) {
            DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->update([
                'is_live' => 1,
                'date_activated' => $today,
            ]);
        }

        $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->first();
        return $this->corsJson($prop->is_live ?? null, 200)->header('Access-Control-Allow-Methods', '*');
    }

    public function onClickLock(Request $request, $id)
    {
        $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->first();
        if (!$prop) {
            return $this->corsJson(['error' => 'Property not found'], 404);
        }

        $status = (int) $request->input('status');
        DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->update([
            'status' => $status === 1 ? 0 : 1,
        ]);

        $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->first();
        return $this->corsJson($prop->status ?? null, 200)->header('Access-Control-Allow-Methods', '*');
    }

    public function PropToErp($property, $mode)
    {
        $prop = is_array($property) ? (object) $property : $property;
        $descript = $this->trim_all($prop->description ?? '');
        $cancelPolicy = $this->trim_all($prop->cancellation_policy ?? '');
        $keywords = $this->trim_all($prop->keywords ?? '');
        $terms = $this->trim_all($prop->terms_conditions ?? '');

        $listingType = DB::table('virtualdesigns_properties_listing_types')->where('id', '=', $prop->listing_type_id)->first();
        $suburb = DB::table('virtualdesigns_locations_locations')->where('id', '=', $prop->suburb_id)->first();
        $rateType = DB::table('virtualdesigns_properties_rate_bases')->where('id', '=', $prop->rate_basis_id)->first();
        $extraCharges = DB::table('virtualdesigns_extracharges_extracharges')->where('property_id', '=', $prop->id)->first();
        $opInfo = DB::table('virtualdesigns_operationalinformation_operationalinformation')->where('property_id', '=', $prop->id)->first();

        $minRate = $prop->min_rate !== null ? number_format((float) $prop->min_rate, 2, '.', '') : null;

        $apiArray = [];
        $apiArray[$prop->id] = [
            'aa_mysql_id' => $prop->id,
            'name' => $prop->name,
            'internal_ref' => $prop->id,
            'web_url' => $prop->booknow_url,
            'property_type' => $listingType->name ?? null,
            'suburb' => $suburb->name ?? null,
            'rate_type' => $rateType->name ?? null,
            'nr_of_bed' => $prop->bed_num,
            'nr_of_room' => $prop->bedroom_num,
            'nr_of_bath' => $prop->bathroom_num,
            'check_in_time' => $prop->checkin_time ?? '10:00:00',
            'check_out_time' => $prop->checkout_time ?? '14:00:00',
            'status' => $prop->is_live,
            'prop_sleeps' => $prop->capacity,
            'prop_X' => $prop->latitude,
            'prop_Y' => $prop->longitude,
            'prop_comm' => $prop->comm_percent,
            'nb_bbid' => $prop->nb_id,
            'avail_id' => $prop->nb_id,
            'cancel_policy' => $cancelPolicy ?: null,
            'keywords' => $keywords ?: null,
            'meta_title' => $prop->meta_title ?: null,
            'meta_desc' => $prop->meta_description ?: null,
            'prop_views' => $prop->scenery ?: null,
            'terms' => $terms ?: null,
            'nb_rtid' => $prop->bbrtid,
            'address' => $prop->physical_address,
            'base_price' => $minRate,
            'grading' => $prop->star_grading === 'None' ? null : $prop->star_grading,
            'descript' => $descript ?: null,
            'min_days_stay' => $prop->min_stay ?: null,
            'prop_wp_amount' => $extraCharges->welcome_pack ?? null,
            'prop_arrival_amount' => $extraCharges->arrival_clean ?? null,
            'prop_departure_amount' => $extraCharges->departure_clean ?? null,
            'prop_hk_amount' => $extraCharges->basic_housekeeping ?? null,
            'prop_dep_laundry_amount' => $extraCharges->fanote_prices ?? null,
            'prop_netflix_amount' => $extraCharges->netflix_costs ?? null,
            'prop_dstv_amount' => $extraCharges->dstv_costs ?? null,
            'prop_wifi_amount' => $extraCharges->wifi_costs ?? null,
            'prop_pool_amount' => $extraCharges->pool_cleaning_costs ?? null,
            'p_booking_fee_amount' => $prop->booking_fee ?? null,
            'p_midstay_amount' => $extraCharges->mid_stay_clean ?? null,
            'p_dhk_amount' => $extraCharges->deluxe_housekeeping ?? null,
            'p_conarr_amount' => $extraCharges->concierge_fee_arrival ?? null,
            'p_condep_amount' => $extraCharges->concierge_fee_departure ?? null,
            'p_concall_amount' => $extraCharges->concierge_callout ?? null,
            'p_maintcall_amount' => $extraCharges->maintenance_callout ?? null,
            'p_laundrypkg_amount' => $extraCharges->laundry_costs ?? null,
            'p_mmf_amount' => $extraCharges->monthly_management_fee ?? null,
            'p_carpet_amount' => $extraCharges->carpet_clean ?? null,
            'p_deepclean_amount' => $extraCharges->deep_clean ?? null,
            'p_winclean_amount' => $extraCharges->window_clean ?? null,
            'p_managed' => $prop->managed,
            'de_zalze' => $prop->de_zalze_comm,
            'cs_check_in_clean' => $opInfo->cs_check_in_clean ?? 'NA',
            'cs_check_out_clean' => $opInfo->cs_check_out_clean ?? 'NA',
            'cleaning_notes' => $opInfo->cleaning_notes ?? 'NA',
            'property_manager' => $prop->user_id,
        ];

        $params = [
            'jsonrpc' => '2.0',
            'params' => $apiArray,
        ];

        $ch = curl_init('http://169.239.181.137:8069/property/update');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($params),
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response);
    }

    public function trim_all($str, $what = null, $with = ' ')
    {
        if ($what === null) {
            $what = "\\x00-\\x20";
        }

        return trim(preg_replace("/[" . $what . "]+/", $with, $str), $what);
    }

    public function syncWithRentalsUnited(Request $request)
    {
        try {
            $this->assertApiKey($request);

            $propertyId = (int) ($request->header('propid') ?? $request->input('propid', 0));

            $propertiesQuery = DB::table('virtualdesigns_properties_properties')
                ->whereNull('deleted_at');

            if ($propertyId > 0) {
                $propertiesQuery->where('id', '=', $propertyId);
            }

            $properties = $propertiesQuery->get();
            $rentalsUnited = new RentalsUnitedService();
            $results = [];

            foreach ($properties as $property) {
                if (!$property->listing_type_id || (!$property->country_id && !$property->city_id)) {
                    $results[] = [
                        'property_id' => $property->id,
                        'status' => 'Skipped',
                        'message' => 'Missing listing type or location',
                    ];
                    continue;
                }

                if ($property->rentals_united_id) {
                    $response = $rentalsUnited->put((int) $property->id, (int) $property->rentals_united_id);
                } else {
                    $response = $rentalsUnited->push((int) $property->id);
                    if (is_object($response) && ($response->Status ?? null) === 'Success' && isset($response->ID)) {
                        DB::table('virtualdesigns_properties_properties')
                            ->where('id', '=', $property->id)
                            ->update(['rentals_united_id' => (int) $response->ID]);
                    }
                }

                $results[] = [
                    'property_id' => $property->id,
                    'response' => $this->normalizeXmlResponse($response),
                ];
            }

            return $this->corsJson(['data' => $results], 200);
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }
            return $this->corsJson(['error' => $e->getMessage()], 500);
        }
    }

    public function getRentalsUnitedProperties(Request $request)
    {
        try {
            $this->assertApiKey($request);

            $propertyId = (int) ($request->header('propid') ?? $request->input('propid', 0));
            if ($propertyId <= 0) {
                return $this->corsJson(['error' => 'Property id is required'], 400);
            }

            $rentalsUnited = new RentalsUnitedService();
            $response = $rentalsUnited->getRUProperties($propertyId);

            return $this->corsJson($this->normalizeXmlResponse($response), 200);
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }
            return $this->corsJson(['error' => $e->getMessage()], 500);
        }
    }

    public function sendWelcomeLetterPreview(Request $request)
    {
        try {
            $this->assertApiKey($request);

            $propertyId = (int) ($request->header('propid') ?? $request->input('propid', 0));
            $email = $request->header('email') ?? $request->input('email');

            if ($propertyId <= 0 || !$email) {
                return $this->corsJson(['error' => 'Property id and email are required'], 400);
            }

            $property = DB::table('virtualdesigns_properties_properties')->where('id', '=', $propertyId)->first();
            if (!$property) {
                return $this->corsJson(['error' => 'Property not found'], 404);
            }

            $today = date('Y-m-d');

            $booking = DB::table('virtualdesigns_erpbookings_erpbookings')
                ->where('status', '!=', 1)
                ->whereNull('deleted_at')
                ->where('arrival_date', '>=', $today)
                ->where('property_id', '=', $propertyId)
                ->orderBy('arrival_date')
                ->first();

            $opInfo = DB::table('virtualdesigns_operationalinformation_operationalinformation')
                ->where('property_id', '=', $propertyId)
                ->first();

            $propManager = DB::table('users')->where('id', '=', $property->user_id)->first();
            $managerInfo = DB::table('virtualdesigns_clientinformation_')
                ->where('user_id', '=', $property->user_id)
                ->first();

            $managerContactNumber = $managerInfo->contact_number ?? null;
            $managerName = $propManager
                ? trim(($propManager->name ?? '') . ' ' . ($propManager->surname ?? ''))
                : 'Host Agents';

            $imageUrl = '';
            $imageRecord = DB::table('system_files')
                ->where('attachment_type', '=', 'Virtualdesigns\\Properties\\Models\\Property')
                ->where('attachment_id', '=', $propertyId)
                ->where('field', '=', 'image_gallery')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();

            if ($imageRecord) {
                $imageUrl = Storage::disk('public')->url($imageRecord->disk_name);
            }

            if ($booking) {
                $clientName = $booking->client_name;
                $arrivalDate = $booking->arrival_date;
                $departureDate = $booking->departure_date;
                $bookingRef = $booking->booking_ref;
                $bookingId = $booking->booking_id;
            } else {
                $clientName = 'Test Client';
                $arrivalDate = date('Y-m-d');
                $departureDate = date('Y-m-d', strtotime($arrivalDate . ' +4 day'));
                $bookingRef = 'Test';
                $bookingId = 0;
            }

            $vars = [
                'client_name' => $clientName,
                'client_email' => $email,
                'prop_name' => $property->name,
                'prop_manager_name' => $managerName,
                'prop_manager_phone' => $managerContactNumber,
                'arrival_date' => date('d/m/Y', strtotime($arrivalDate)),
                'departure_date' => date('d/m/Y', strtotime($departureDate)),
                'booking_ref' => $bookingRef,
                'booking_id' => $bookingId,
                'physical_address' => $property->physical_address,
                'prop_lat' => $property->latitude,
                'prop_long' => $property->longitude,
                'directions_link' => $property->directions_link,
                'parking_notes' => $opInfo->parking_notes ?? null,
                'guest_checkin_info' => $opInfo->guest_checkin_info ?? null,
                'wifi_username' => $opInfo->wifi_username ?? null,
                'wifi_password' => $opInfo->wifi_password ?? null,
                'tv_instructions' => $opInfo->tv_instructions ?? null,
                'meter_number' => $opInfo->meter_number ?? null,
                'refuse_collection_notes' => $opInfo->refuse_collection_notes ?? null,
                'guest_info' => $opInfo->guest_info ?? null,
                'guest_departure_info' => $opInfo->guest_departure_info ?? null,
                'inv_url' => $property->inventory_url ?? null,
                'prop_photo' => $imageUrl,
            ];

            $altEmail = null;
            if ($booking) {
                $guestInfo = DB::table('virtualdesigns_erpbookings_guestinfo')
                    ->where('booking_id', '=', $booking->id)
                    ->first();
                if ($guestInfo && !empty($guestInfo->guest_alternative_email_address) && str_contains($guestInfo->guest_alternative_email_address, '@')) {
                    $altEmail = $guestInfo->guest_alternative_email_address;
                }
            }

            $attachments = [];
            if ($opInfo) {
                $attachments = DB::table('system_files')
                    ->where('attachment_type', '=', 'Virtualdesigns\\Operationalinformation\\Models\\Operationalinformation')
                    ->where('attachment_id', '=', $opInfo->id)
                    ->where('field', '=', 'welcome_attachments')
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get();
            }

            Mail::send('mail.welcome_letter', $vars, function ($message) use ($vars, $altEmail, $attachments) {
                $message->to($vars['client_email']);
                if ($altEmail) {
                    $message->cc($altEmail);
                }
                $message->subject('Welcome Letter - ' . $vars['prop_name']);

                foreach ($attachments as $attachment) {
                    if (Storage::disk('public')->exists($attachment->disk_name)) {
                        $message->attach(Storage::disk('public')->path($attachment->disk_name));
                    }
                }
            });

            return $this->corsJson('success', 200);
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }
            return $this->corsJson(['error' => $e->getMessage()], 500);
        }
    }

    public function SyncRatesAvail($id)
    {
        $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->first();
        if (!$prop || $prop->pricelabs_id === null) {
            return $this->corsJson(['error' => 'Property not found'], 404);
        }

        $data = json_encode([
            'listings' => [[
                'id' => (string) $prop->pricelabs_id,
                'pms' => 'rentalsunited',
            ]],
        ]);

        $endpoint = 'https://api.pricelabs.co/v1/listing_prices';
        $apiKey = 'TaZDoGI3cRzedjAhRTXVVrMOD8UwnuQc8qu9TRRr';

        $curl = curl_init($endpoint);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-API-Key: ' . $apiKey,
        ]);

        $rates = json_decode(curl_exec($curl));
        curl_close($curl);

        if (!is_array($rates)) {
            return $this->corsJson($rates, 200);
        }

        foreach ($rates as $rate) {
            foreach ($rate->data ?? [] as $day) {
                return $this->corsJson($day, 200);
            }
        }

        return $this->corsJson([], 200);
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

    private function logChange($userId, $recordId, string $field, $oldValue, $newValue): void
    {
        DB::table('virtualdesigns_changes_changes')->insert([
            'user_id' => $userId,
            'db_table' => 'virtualdesigns_properties_properties',
            'record_id' => $recordId,
            'field' => $field,
            'old' => $oldValue,
            'new' => $newValue,
            'change_date' => date('Y-m-d H:i:s'),
        ]);
    }

    private function filterPropertyFields(array $data): array
    {
        return array_intersect_key($data, array_flip($this->propertyUpdateFields()));
    }

    private function propertyUpdateFields(): array
    {
        return [
            'admin_approved',
            'bathroom_num',
            'bed_num',
            'bedroom_num',
            'bill_owner_cleans',
            'bill_owner_laundry',
            'block_start',
            'booknow_url',
            'capacity',
            'child_policy',
            'city_id',
            'comm_percent',
            'comm_update_required',
            'concierge_id',
            'damage_deposit',
            'de_zalze_comm',
            'description',
            'direct_comm',
            'establishment_layout',
            'floor',
            'ha_packed',
            'has_block',
            'hide_prop',
            'inventory_url',
            'listing_type_id',
            'live_override',
            'managed',
            'name',
            'owner_arrival_clean',
            'owner_departure_clean',
            'owner_mid_stay',
            'owner_welcome_pack',
            'physical_address',
            'portfolio_reservationist_id',
            'postal_code',
            'pricelabs_id',
            'prop_weighting',
            'province_id',
            'rate_basis_id',
            'rentals_united_property_type',
            'reservation_email',
            'scenery',
            'service_fee',
            'show_bhr',
            'show_home',
            'square_meters',
            'star_grading',
            'suburb_id',
            'terms_conditions',
            'unit_number',
            'user_email',
            'user_id',
            'bd_override',
            'bodycorp_id',
            'portfolio_manager_id',
            'country_id',
            'prop_video_url',
            'directions',
            'directions_link',
            'latitude',
            'longitude',
            'property_type',
            'last_minute',
            'as_room',
            'bbrtid',
            'live_integration',
            'nb_id',
            'slug',
        ];
    }

    private function updatePropertyLocations(array $_regions, $propertyId): void
    {
        $property = DB::table('virtualdesigns_properties_properties')->where('id', '=', $propertyId)->first();
        if (!$property) {
            return;
        }

        $propertyLocations = [];
        if ($property->province_id !== null) {
            $propertyLocations[] = $property->province_id;
        }
        if ($property->city_id !== null) {
            $propertyLocations[] = $property->city_id;
        }
        if ($property->suburb_id !== null) {
            $propertyLocations[] = $property->suburb_id;
        }

        foreach ($_regions as $regionId) {
            $propertyLocations[] = $regionId;
        }

        $propertyLocations = array_values(array_unique($propertyLocations));

        DB::table('virtualdesigns_locations_locations_properties')->where('property_id', '=', $propertyId)->delete();

        foreach ($propertyLocations as $locationId) {
            DB::table('virtualdesigns_locations_locations_properties')->insert([
                'property_id' => $propertyId,
                'location_id' => $locationId,
            ]);
        }

        DB::table('virtualdesigns_properties_properties')->where('id', '=', $propertyId)->update([
            'locations' => json_encode($propertyLocations),
        ]);
    }

    private function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo, $earthRadius = 6371)
    {
        $latFrom = deg2rad((float) $latitudeFrom);
        $lonFrom = deg2rad((float) $longitudeFrom);
        $latTo = deg2rad((float) $latitudeTo);
        $lonTo = deg2rad((float) $longitudeTo);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));

        return $angle * $earthRadius;
    }

    protected function getPaginatorSlice($results, $page)
    {
        return array_slice($results, ($page - 1) * 20, 20);
    }

    private function normalizeXmlResponse($response)
    {
        if ($response instanceof \SimpleXMLElement) {
            return json_decode(json_encode($response), true);
        }

        return $response;
    }
}
