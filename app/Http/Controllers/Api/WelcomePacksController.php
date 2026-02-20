<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WelcomePacksController extends Controller
{
    public function index(Request $request)
    {
        $this->assertApiKey($request);

        $start = $request->header('startdate') ?? date('Y-m-d');
        $end = $request->header('enddate') ?? date('Y-m-d', strtotime('+1 day'));
        $startDeparture = $request->header('startdatedeparture');
        $endDeparture = $request->header('enddatedeparture');

        if ($startDeparture) {
            $start = $startDeparture;
        }
        if ($endDeparture) {
            $end = $endDeparture;
        }

        $filterProp = $request->header('propid');
        $filterSuburb = $request->header('suburb');
        $filterStatus = $request->header('packstatus');
        $filterClientName = $request->header('clientname');
        $filterHostId = $request->header('hostid');
        $filterManagerId = $request->header('mangerid');
        $filterBookingRef = $request->header('bookingref');

        $bookings = DB::table('virtualdesigns_erpbookings_erpbookings')
            ->where('status', '!=', 1)
            ->whereNull('deleted_at')
            ->where('quote_confirmed', '=', 1);

        if ($startDeparture) {
            $bookings->where('departure_date', '>=', $start)
                ->where('departure_date', '<=', $end);
        } else {
            $bookings->where('arrival_date', '>=', $start)
                ->where('arrival_date', '<=', $end);
        }

        if ($filterProp) {
            $bookings->where('property_id', '=', $filterProp);
        }
        if ($filterClientName) {
            $bookings->where('client_name', '=', $filterClientName);
        }
        if ($filterBookingRef) {
            $bookings->where('booking_ref', '=', $filterBookingRef);
        }

        $bookingIds = $bookings->pluck('id')->toArray();
        $bookingProps = $bookings->pluck('property_id')->toArray();
        $bookingsList = $bookings->get();

        $props = DB::table('virtualdesigns_properties_properties')
            ->whereIn('id', $bookingProps)
            ->get();

        $propertyNames = [];
        $propertyHosts = [];
        $portfolioManagers = [];
        $suburbs = [];

        foreach ($props as $prop) {
            if (!isset($portfolioManagers[$prop->portfolio_manager_id]) && $prop->portfolio_manager_id) {
                $pm = DB::table('users')->where('id', '=', $prop->portfolio_manager_id)->first();
                if ($pm) {
                    $portfolioManagers[$prop->portfolio_manager_id] = [
                        'id' => $prop->portfolio_manager_id,
                        'name' => trim($pm->name . ' ' . $pm->surname),
                    ];
                }
            }

            if (!isset($suburbs[$prop->suburb_id]) && $prop->suburb_id) {
                $propSuburb = DB::table('virtualdesigns_locations_locations')->where('id', '=', $prop->suburb_id)->first();
                if ($propSuburb) {
                    $suburbs[$prop->suburb_id] = [
                        'id' => $prop->suburb_id,
                        'name' => $propSuburb->name,
                    ];
                }
            }

            if (!isset($propertyNames[$prop->id])) {
                $propertyNames[$prop->id] = [
                    'id' => $prop->id,
                    'name' => $prop->name,
                ];
            }
        }

        $bookingRefs = [];
        $clientNames = [];

        foreach ($bookingsList as $booking) {
            $bookingRefs[] = [
                'id' => $booking->booking_ref,
                'name' => $booking->booking_ref,
            ];
            $clientNames[] = [
                'id' => $booking->client_name,
                'name' => $booking->client_name,
            ];
        }

        $jobCards = DB::table('virtualdesigns_cleans_cleans')
            ->whereIn('booking_id', $bookingIds)
            ->where('clean_type', '=', 'Welcome Pack')
            ->where('status', '!=', 1)
            ->whereNull('deleted_at');

        if ($filterStatus) {
            $jobCards->where('pack_status', '=', $filterStatus);
        }

        $jobCards = $jobCards->get();
        $jobCardsFinal = [];

        foreach ($jobCards as $jobCard) {
            $jobBooking = DB::table('virtualdesigns_erpbookings_erpbookings')
                ->where('id', '=', $jobCard->booking_id)
                ->first();

            if (!$jobBooking) {
                continue;
            }

            $startTs = strtotime($jobBooking->arrival_date);
            $endTs = strtotime($jobBooking->departure_date);
            $jobCard->nights = round(($endTs - $startTs) / 86400);
            $jobCard->related_booking = $jobBooking;
            $jobCard->arrival_date = $jobBooking->arrival_date;
            $jobCard->departure_date = $jobBooking->departure_date;
            $jobCard->booking_ref = $jobBooking->booking_ref;
            $jobCard->client_name = $jobBooking->client_name;

            $jobProp = DB::table('virtualdesigns_properties_properties')
                ->where('id', '=', $jobCard->property_id)
                ->first();

            if ($jobProp) {
                $jobCard->related_property = $jobProp;
                $jobCard->property_name = $jobProp->name;

                $welcomePack = DB::table('virtualdesigns_welcomepacks_welcomepacks')
                    ->whereNull('deleted_at')
                    ->where('property_id', '=', $jobProp->id)
                    ->first();

                $jobCard->welcome_pack = $welcomePack;
                $jobCard->milk_pods = $welcomePack->milk_pods ?? null;
                $jobCard->coffee_sachet = $welcomePack->coffee_sachet ?? null;
                $jobCard->tea_five_roses = $welcomePack->tea_five_roses ?? null;
                $jobCard->suger_sachet_brown = $welcomePack->suger_sachet_brown ?? null;
                $jobCard->toilet_paper = $welcomePack->toilet_paper ?? null;
                $jobCard->sunlight_liquid = $welcomePack->sunlight_liquid ?? null;
                $jobCard->washing_up_sponge = $welcomePack->washing_up_sponge ?? null;
                $jobCard->microfiber_cloth = $welcomePack->microfiber_cloth ?? null;
                $jobCard->black_bags = $welcomePack->black_bags ?? null;
                $jobCard->ariel_laundry_capsules = $welcomePack->ariel_laundry_capsules ?? null;
                $jobCard->finish_dishwasher_tablets = $welcomePack->finish_dishwasher_tablets ?? null;
                $jobCard->conditioning_shampoo = $welcomePack->conditioning_shampoo ?? null;
                $jobCard->shower_gel = $welcomePack->shower_gel ?? null;
                $jobCard->hand_soap = $welcomePack->hand_soap ?? null;
                $jobCard->nespresso_pods = $welcomePack->nespresso_pods ?? null;
                $jobCard->rooibos_tea = $welcomePack->rooibos_tea ?? null;

                if ($jobProp->portfolio_manager_id) {
                    $jobPm = DB::table('users')->where('id', '=', $jobProp->portfolio_manager_id)->first();
                    $jobCard->property_manager = $jobPm;
                    $jobCard->property_manager_name = $jobPm ? trim($jobPm->name . ' ' . $jobPm->surname) : null;
                    $jobCard->property_manager_id = $jobPm->id ?? null;
                } else {
                    $jobCard->property_manager = null;
                    $jobCard->property_manager_name = null;
                    $jobCard->property_manager_id = null;
                }

                if ($jobProp->suburb_id) {
                    $propSuburb = DB::table('virtualdesigns_locations_locations')->where('id', '=', $jobProp->suburb_id)->first();
                    $jobCard->suburb = $propSuburb;
                    $jobCard->suburb_name = $propSuburb->name ?? null;
                    $jobCard->suburb_id = $propSuburb->id ?? null;
                } else {
                    $jobCard->suburb = null;
                    $jobCard->suburb_name = null;
                    $jobCard->suburb_id = null;
                }
            } else {
                $jobCard->related_property = null;
                $jobCard->property_name = null;
                $jobCard->welcome_pack = null;
                $jobCard->property_manager = null;
                $jobCard->property_manager_name = null;
                $jobCard->property_manager_id = null;
            }

            $jobSupplier = DB::table('users')->where('id', '=', $jobCard->supplier_id)->first();
            if (!isset($propertyHosts[$jobCard->supplier_id]) && $jobSupplier) {
                $propertyHosts[$jobCard->supplier_id] = [
                    'id' => $jobCard->supplier_id,
                    'name' => trim($jobSupplier->name . ' ' . $jobSupplier->surname),
                ];
            }

            $jobCard->property_host = $jobSupplier;
            $jobCard->property_host_name = $jobSupplier ? trim($jobSupplier->name . ' ' . $jobSupplier->surname) : null;
            $jobCard->property_host_id = $jobSupplier->id ?? null;

            $passFilter = 1;
            if ($filterSuburb && $jobProp && $jobProp->suburb_id != $filterSuburb) {
                $passFilter = 0;
            }
            if ($filterHostId && $jobCard->supplier_id != $filterHostId) {
                $passFilter = 0;
            }
            if ($filterManagerId && $jobProp && $jobProp->portfolio_manager_id != $filterManagerId) {
                $passFilter = 0;
            }
            if ($jobProp && $jobProp->ha_packed != 1) {
                $passFilter = 0;
            }

            if ($passFilter === 1) {
                $jobCardsFinal[] = $jobCard;
            }
        }

        $data = [
            'jobcards' => $jobCardsFinal,
            'properties' => $props,
            'bookings' => $bookingsList,
            'booking_refs' => $bookingRefs,
            'client_names' => $clientNames,
            'property_names' => $propertyNames,
            'property_hosts' => $propertyHosts,
            'portfolio_managers' => $portfolioManagers,
            'suburbs' => $suburbs,
        ];

        return $this->corsJson($data, 200);
    }

    public function show(Request $request, int $id)
    {
        $this->assertApiKey($request);

        $welcomePack = DB::table('virtualdesigns_welcomepacks_welcomepacks')
            ->where('property_id', '=', $id)
            ->first();
        $prop = DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->first();

        if ($welcomePack && $prop) {
            $welcomePack->beds = $prop->bedroom_num;
            $welcomePack->baths = $prop->bathroom_num;
        }

        return $this->corsJson($welcomePack, 200);
    }

    public function store(Request $request)
    {
        $this->assertApiKey($request);

        $payload = $request->all();
        $payload['created_at'] = now();

        DB::table('virtualdesigns_welcomepacks_welcomepacks')->insert($payload);

        return $this->corsJson('success', 200);
    }

    public function update(Request $request, int $id)
    {
        $this->assertApiKey($request);

        $payload = $request->all();
        $payload['updated_at'] = now();

        DB::table('virtualdesigns_welcomepacks_welcomepacks')
            ->where('id', '=', $id)
            ->update($payload);

        return $this->corsJson('success', 200);
    }

    public function destroy(Request $request, int $id)
    {
        $this->assertApiKey($request);

        DB::table('virtualdesigns_welcomepacks_welcomepacks')
            ->where('id', '=', $id)
            ->update(['deleted_at' => now()]);

        return $this->corsJson('success', 200);
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
