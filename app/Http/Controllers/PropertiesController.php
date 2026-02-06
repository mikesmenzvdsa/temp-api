<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class PropertiesController extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    public function index()
    {
        try {
            //Creates header array and populate with header values
            $headers = array();
            foreach (getallheaders() as $name => $value) {
                $headers[strtolower($name)] = $value;
            }

            $apikey = $headers['key'];

            //Get data from post
            if (md5("aiden@virtualdesigns.co.za3d@=kWfmMR") == $apikey) {
                //Get list of properties based on user group and return
                try {
                    $property_recs = DB::table('virtualdesigns_properties_properties')->where('virtualdesigns_properties_properties.is_live', '=', 1)->where('virtualdesigns_properties_properties.deleted_at', '=', null)
                        ->Leftjoin('users as owner', 'virtualdesigns_properties_properties.owner_id', '=', 'owner.id')
                        ->Leftjoin('virtualdesigns_clientinformation_ as owner_info', 'virtualdesigns_properties_properties.owner_id', '=', 'owner_info.user_id')
                        ->Leftjoin('virtualdesigns_extracharges_extracharges as fees', 'virtualdesigns_properties_properties.id', '=', 'fees.property_id')
                        ->Leftjoin('virtualdesigns_locations_locations as suburb', 'virtualdesigns_properties_properties.suburb_id', '=', 'suburb.id')
                        ->Leftjoin('virtualdesigns_locations_locations as city', 'virtualdesigns_properties_properties.city_id', '=', 'city.id')
                        ->select(
                            'virtualdesigns_properties_properties.*',
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

                    return response($property_recs, 200)
                        ->header('Content-Type', 'application.json')
                        ->header('Access-Control-Allow-Origin', '*');
                } catch (Exception $e) {
                    //If error, sets header and returns error message
                    return response($e, 500)
                        ->header('Content-Type', 'application.json')
                        ->header('Access-Control-Allow-Origin', '*');
                }
            } else {
                //Return error if incorrect API key is used
                $error_array = array(
                    "code" => 401,
                    "message" => "Wrong API Key"
                );
                return response(json_encode($error_array), 401)
                    ->header('Content-Type', 'application.json')
                    ->header('Access-Control-Allow-Origin', '*');
            }
        } catch (Exception $e) {
            //If error, sets header and returns error message
            return response($e, 500)
                ->header('Content-Type', 'application.json')
                ->header('Access-Control-Allow-Origin', '*');
        }
    }
}
