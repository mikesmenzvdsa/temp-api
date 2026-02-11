<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function product(string $page): JsonResponse
    {
        Log::debug("page: --" . $page . "--");

        if (view()->exists("pages.product.{$page}")) {

            $bookings = DB::connection('remote_test')
                ->table('virtualdesigns_erpbookings_erpbookings as booking')
                ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                ->Leftjoin('virtualdesigns_bodycorp_bodycorp as body_corp', 'body_corp.id', '=', 'prop.bodycorp_id')
                ->Leftjoin('virtualdesigns_erpbookings_guestinfo as guestinfo', 'guestinfo.booking_id', '=', 'booking.id')
                ->where('booking.status', '!=', 1)
                ->where('booking.deleted_at', '=', null)
                ->where('booking.quote_confirmed', '=', 1)
                ->where('booking.arrival_date', '=', date('Y-m-d'))
                ->select(
                    'prop.name as prop_name',
                    'prop.id as prop_id',
                    'booking.arrival_date',
                    'booking.departure_date',
                    'body_corp.*',
                    'guestinfo.id as guestinfo_id',
                    'guestinfo.guest_name as guestinfo_guest_name',
                    'guestinfo.guest_id_no as guestinfo_guest_id_no',
                    'guestinfo.guest_contact as guestinfo_guest_contact',
                    'guestinfo.guest_no as guestinfo_guest_no',
                    'guestinfo.eta as guestinfo_eta',
                    'guestinfo.etd as guestinfo_etd',
                    'guestinfo.flight_number as guestinfo_flight_number',
                    'guestinfo.bank_ac_name as guestinfo_bank_ac_name',
                    'guestinfo.bank_ac_no as guestinfo_bank_ac_no',
                    'guestinfo.bank_name as guestinfo_bank_name',
                    'guestinfo.bank_code as guestinfo_bank_code',
                    'guestinfo.no_smoking as guestinfo_no_smoking',
                    'guestinfo.noise_policy as guestinfo_noise_policy',
                    'guestinfo.fair_usage_policy as guestinfo_fair_usage_policy',
                    'guestinfo.breakage_policy as guestinfo_breakage_policy',
                    'guestinfo.terms_conditions as guestinfo_terms_conditions',
                    'guestinfo.vehicle_reg as guestinfo_vehicle_reg',
                    'guestinfo.completed as guestinfo_completed',
                    'guestinfo.booking_id as guestinfo_booking_id',
                    'guestinfo.bank_type as guestinfo_bank_type',
                    'guestinfo.swift_code as guestinfo_swift_code',
                    'guestinfo.pay_type as guestinfo_pay_type',
                    'guestinfo.guest_alternative_email_address as guestinfo_guest_alternative_email_address',
                    'guestinfo.guest_id as guestinfo_guest_id',
                    'guestinfo.vehicle_image_path as guestinfo_vehicle_image_path',
                    'guestinfo.selfie_image_path as guestinfo_selfie_image_path',
                    'guestinfo.other_guests_data as guestinfo_other_guests_data',
                    'prop.capacity',
                    'booking.client_name',
                    'booking.client_phone',
                    'booking.booking_ref',
                    'booking.id as booking_id',
                    'guestinfo.mail_sent_to_body_corp as mail_sent',
                    'guestinfo.available_vehicle_status',
                )
                ->get();

            $responseData = [
                'page' => $page,
                'bookings' => $bookings,
            ];

            if ($page == 'dashboard') {

                $rules_count = array(
                        'body_corp_to_send',
                        'body_corp_full_names_required',
                        'body_corp_vehicle_reg_required',
                        'body_corp_id_selfies_required',
                        'body_corp_all_guest_contacts_required',
                        'body_corp_all_guest_id_img_required',
                        'body_corp_main_guest_name_and_phone_number_required',
                        'main_guest_name_phone_number_and_id_number_image_upload_required'
                    );
                $body_corps = DB::connection('remote_test')
                    ->table('virtualdesigns_bodycorp_bodycorp')
                    ->where('virtualdesigns_bodycorp_bodycorp.deleted_at', '=', null)
                    ->get();


                $body_corps_count = $body_corps->count();
                $responseData['title'] = 'Product';
                $responseData['body_corps_count'] = $body_corps_count;
                $responseData['rules_count'] = $rules_count;
                return response()->json(['success' => true, 'data' => $responseData]);
            } elseif ($page == 'check-in-rules') {
                $body_corps = DB::connection('remote_test')
                    ->table('virtualdesigns_bodycorp_bodycorp')
                    ->where('virtualdesigns_bodycorp_bodycorp.deleted_at', '=', null)
                    ->get();
                $responseData['title'] = 'Manage Guest Checkin Rules';
                $responseData['body_corps'] = $body_corps;
                return response()->json(['success' => true, 'data' => $responseData]);
                //Logic to load data for Send Guest Registration section
            } else {
                return response()->json(['success' => true, 'data' => $responseData]);
            }
        }
        return response()->json(['success' => false, 'message' => 'Page not found.'], 404);
    }

    public function updateproduct(Request $request): JsonResponse
    {

        $guest_sent_reg = $request->all();
        $body_corp_id = $request->body_corp_id;
        if (isset($request->notes_only)) {

            DB::connection('remote_test')->table('virtualdesigns_bodycorp_bodycorp')
                ->where('id', '=', $body_corp_id)
                ->where('deleted_at', '=', $body_corp_id)
                ->update([
                    "notes" => $request->body_corp_notes,
                    "updated_at" => date("Y-m-d H:i:s")
                ]);
            $searchTerm = "Guest Registration";
            $bookings = DB::connection('remote_test')
                ->table('virtualdesigns_erpbookings_erpbookings as booking')
                ->where('booking.status', '!=', 1)
                ->where('booking.deleted_at', '=', null)
                ->where('booking.quote_confirmed', '=', 1)
                ->where('booking.arrival_date', '=', date('Y-m-d'))
                ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                ->crossJoin(DB::raw("(SELECT * 
                        FROM virtualdesigns_bodycorp_bodycorp 
                        ORDER BY id ASC 
                        LIMIT 1) as body_corp"))
                ->select(
                    'prop.name as prop_name',
                    'booking.arrival_date',
                    'booking.departure_date',
                    'body_corp.*',
                    'booking.client_name',
                    'booking.client_phone',
                    'booking.booking_ref',
                    'booking.id as booking_id',
                )
                ->get();
            $prop_recs = DB::connection('remote_test')
                ->table('virtualdesigns_properties_properties')
                ->where('deleted_at', '=', null)
                ->orderBy('name')
                ->get();
            $uniqueBookingRefs = $bookings->pluck('booking_ref')->unique()->sort()->values()->toArray();
            $uniqueGuestNames = $bookings->pluck('client_name')->unique()->sort()->values()->toArray();
            $uniqueBodyCorps = $bookings->pluck('body_corp_name')->unique()->sort()->values()->toArray();
            $message_sent = "Notes Updated Succesfully";
            return response()->json([
                'success' => true,
                'message' => $message_sent,
                'data' => [
                    'bookings' => $bookings,
                    'prop_recs' => $prop_recs,
                    'uniqueBookingRefs' => $uniqueBookingRefs,
                    'uniqueGuestNames' => $uniqueGuestNames,
                    'uniqueBodyCorps' => $uniqueBodyCorps,
                ],
            ]);
        } elseif (isset($request->is_filter)) {
            $filter_values = array();
            $searchTerm = "Guest Registration";
            $bookings = DB::connection('remote_test')
                ->table('virtualdesigns_erpbookings_erpbookings as booking')
                ->where('booking.status', '!=', 1)
                ->where('booking.deleted_at', '=', null)
                ->where('booking.quote_confirmed', '=', 1);
            if (isset($request->filter_arrival_date)) {
                if ($request->filter_arrival_date != "" && $request->filter_arrival_date != null) {
                    $bookings = $bookings->where('booking.arrival_date', '=', date('Y-m-d', strtotime($request->filter_arrival_date)));
                    $filter_values['date'] = $request->filter_arrival_date;
                } else {
                    $bookings = $bookings->where('booking.arrival_date', '=', date('Y-m-d'));
                    $filter_values['date'] = $request->filter_arrival_date;
                }
            } else {
                $bookings = $bookings->where('booking.arrival_date', '=', date('Y-m-d'));
                $filter_values['date'] = "";
            }
            if (isset($request->filter_prop)) {
                if ($request->filter_prop > 0) {
                    $bookings = $bookings->where('prop.id', '=', $request->filter_prop);
                }
                $filter_values['prop'] = $request->filter_prop;
            } else {
                $filter_values['prop'] = 0;
            }
            if (isset($request->filter_body_corp_name)) {
                if ($request->filter_body_corp_name != '-- Select --') {
                    $bookings = $bookings->where('body_corp.body_corp_name', '=', $request->filter_body_corp_name);
                }
                $filter_values['body_corp_name'] = $request->filter_body_corp_name;
            } else {
                $filter_values['body_corp_name'] = "No result found";
            }
            if (isset($request->filter_guest_name)) {
                if ($request->filter_guest_name != '-- Select --') {
                    $bookings = $bookings->where('booking.client_name', '=', $request->filter_guest_name);
                }
                $filter_values['guest_name'] = $request->filter_guest_name;
            } else {
                $filter_values['guest_name'] = "No result found";
            }
            if (isset($request->filter_booking_ref)) {
                if ($request->filter_booking_ref != '-- Select --') {
                    $bookings = $bookings->where('booking.booking_ref', '=', $request->filter_booking_ref);
                }
                $filter_values['booking_ref'] = $request->filter_booking_ref;
            } else {
                $filter_values['booking_ref'] = "No result found";
            }
            $bookings = $bookings->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                ->crossJoin(DB::raw("(SELECT * 
                        FROM virtualdesigns_bodycorp_bodycorp 
                        ORDER BY id ASC 
                        LIMIT 1) as body_corp"))
                ->select(
                    'prop.name as prop_name',
                    'booking.arrival_date',
                    'booking.departure_date',
                    'body_corp.*',
                    'booking.client_name',
                    'booking.client_phone',
                    'booking.booking_ref',
                    'booking.id as booking_id',
                )
                ->get();
            $prop_recs = DB::connection('remote_test')
                ->table('virtualdesigns_properties_properties')
                ->where('deleted_at', '=', null)
                ->orderBy('name')
                ->get();
            $uniqueBookingRefs = $bookings->pluck('booking_ref')->unique()->sort()->values()->toArray();
            $uniqueGuestNames = $bookings->pluck('client_name')->unique()->sort()->values()->toArray();
            $uniqueBodyCorps = $bookings->pluck('body_corp_name')->unique()->sort()->values()->toArray();
            $bodyCorpDefault = DB::connection('remote_test')->table('virtualdesigns_bodycorp_bodycorp')->where('id', '=', 1)->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'filter_values' => $filter_values,
                    'bookings' => $bookings,
                    'prop_recs' => $prop_recs,
                    'uniqueBookingRefs' => $uniqueBookingRefs,
                    'uniqueGuestNames' => $uniqueGuestNames,
                    'uniqueBodyCorps' => $uniqueBodyCorps,
                    'bodyCorpDefault' => $bodyCorpDefault,
                ],
            ]);
        } elseif (isset($request->corp_deleted_by_id)) {

            $bodycorpid = $request->corp_deleted_by_id;

            $bookings = DB::connection('remote_test')
                ->table('virtualdesigns_bodycorp_bodycorp as body_corp')
                ->where('body_corp.id', '=', $bodycorpid)
                ->update(['body_corp.deleted_at' => date("Y-m-d H:i:s")]);

            $body_corps = DB::connection('remote_test')
                ->table('virtualdesigns_bodycorp_bodycorp')
                ->where('virtualdesigns_bodycorp_bodycorp.deleted_at', '=', null)
                ->get();

            $message_sent = "Body Corporate Deleted Succesfully";
            return response()->json([
                'success' => true,
                'message' => $message_sent,
                'data' => [
                    'body_corps' => $body_corps,
                ],
            ]);
        } elseif (isset($request->guest_booking_id)) {

            $idImagePath = null;
            $vehicleImagePath = null;

            $grouped = [];
            if ($request->send_guest_registration === 'Send Guest Registration') {
                foreach ($guest_sent_reg as $key => $value) {
                    // Check if key ends with an underscore followed by a number
                    if (preg_match('/_(\d+)$/', $key, $matches)) {
                        $index = $matches[1]; // e.g., "2"
                        // Initialize group if it doesn't exist yet
                        if (!isset($grouped[$index])) {
                            $grouped[$index] = [];
                        }
                        // Add the key–value pair to the correct group
                        $grouped[$index][$key] = $value;
                    }
                }
            }

            $groupArrayWithoutNumberedIndex = [];

            foreach ($grouped as  $key => $value) {
                array_push($groupArrayWithoutNumberedIndex, $value);
            }

            // On the following condition we are saving the image to the server. Then we will be using the image path and saving it on the database
            if ($request->hasFile('guest_id_image_upload_form_file')) {
                $file = $request->file('guest_id_image_upload_form_file');
                $extension = $file->getClientOriginalExtension();
                $filename = $request->guest_booking_ref . '-guestid-test.' . $extension;

                // Upload to FTP/SFTP
                $disk = Storage::disk('ftp'); // or 'sftp'
                $disk->putFileAs('images', $file, $filename);

                $idImagePath = 'images/' . $filename;
            }

            if ($request->hasFile('guest_vehicle_registration_image_upload_form_file')) {
                $file = $request->file('guest_vehicle_registration_image_upload_form_file');
                $extension = $file->getClientOriginalExtension();
                $filename = $request->guest_booking_ref . '-guest-vehicle-reg-test.' . $extension;

                // Upload to FTP/SFTP
                $disk = Storage::disk('ftp'); // or 'sftp'
                $disk->putFileAs('images', $file, $filename);
                $vehicleImagePath = 'images/' . $filename;
            }

            // Update the database guestinfo table first
            $getOldGuestInfo = DB::connection('remote_test')->table('virtualdesigns_erpbookings_guestinfo')
                ->where('booking_id', '=', $request->guest_booking_id)
                ->select(
                    "id",
                    "guest_name",
                    "guest_id_no",
                    "guest_contact",
                    "guest_no",
                    "vehicle_reg",
                    "completed",
                    "booking_id",
                    "guest_alternative_email_address",
                    "guest_id",
                    "other_guests_data",
                )->get();

            $updateData = [];

            if ($request->filled('guest_fullname') && $getOldGuestInfo[0]->guest_name !== $request->guest_fullname) {
                $updateData['guest_name'] = $request->guest_fullname;
            } else {
                $updateData = [];
            }
            if ($request->filled('guest_phone_number') && $getOldGuestInfo[0]->guest_contact !== $request->guest_phone_number) {
                $updateData['guest_contact'] = $request->guest_phone_number;
            }
            if ($request->filled('guest_email') && $getOldGuestInfo[0]->guest_alternative_email_address !== $request->guest_email) {
                // $updateData['guest_email'] = $request->guest_email;
                $updateData['guest_alternative_email_address'] = $request->guest_email;
            }
            if ($request->filled('guest_id_number') && $getOldGuestInfo[0]->guest_id_no !== $request->guest_id_number) {
                $updateData['guest_id_no'] = $request->guest_id_number;
            }
            if ($request->filled('guest_vehicle_registration_number') && $getOldGuestInfo[0]->vehicle_reg !== $request->guest_vehicle_registration_number) {
                $updateData['vehicle_reg'] = $request->guest_vehicle_registration_number;
            }
            if ($request->hasFile('guest_id_image_upload_form_file') && $request->hasFile('guest_vehicle_registration_image_upload_form_file')) {
                $updateData['guest_id'] = json_encode(array(env('FTP_ROOT_PATH') . '/' . $idImagePath, env('FTP_ROOT_PATH') . '/' . $vehicleImagePath));
            } elseif ($request->hasFile('guest_id_image_upload_form_file')) {
                $updateData['guest_id'] = env('FTP_ROOT_PATH') . '/' . $idImagePath;
            } elseif ($request->hasFile('guest_vehicle_registration_image_upload_form_file')) {
                $updateData['guest_id'] = env('FTP_ROOT_PATH') . '/' . $vehicleImagePath;
            } elseif (!empty($groupArrayWithoutNumberedIndex)) {
                if (
                    $request->guest_number_of_guest > $getOldGuestInfo[0]->guest_no ||
                    $request->guest_number_of_guest < $getOldGuestInfo[0]->guest_no
                ) {
                    $updateData['guest_no'] = $request->guest_number_of_guest;
                }

                // In this function we want to find the key that has guest_id_image_upload_form_file_ in
                foreach ($groupArrayWithoutNumberedIndex[0] as $key => $value) {

                    $otherIdImagePath = null;
                    $otherVehicleImagePath = null;

                    if (strpos($key, 'guest_id_image_upload_form_file') === 0) {
                        if ($request->hasFile($key)) {

                            $file = $request->file($key);
                            $extension = $file->getClientOriginalExtension();
                            $filename = $request->guest_booking_ref . '-guestid-test.' . $extension;

                            // Upload to FTP/SFTP
                            $disk = Storage::disk('ftp'); // or 'sftp'
                            $disk->putFileAs('images', $file, $filename);

                            $otherIdImagePath = 'images/' . $filename;
                        }
                        $groupArrayWithoutNumberedIndex[0]['other_image_path'] = env('FTP_ROOT_PATH') . '/' . $otherIdImagePath;
                    }

                    if (strpos($key, 'guest_vehicle_registration_image_upload_form_file') === 0) {
                        if ($request->hasFile($key)) {

                            $file = $request->file($key);
                            $extension = $file->getClientOriginalExtension();
                            $filename = $request->guest_booking_ref . '-guest-vehicle-reg-test.' . $extension;

                            // Upload to FTP/SFTP
                            $disk = Storage::disk('ftp'); // or 'sftp'
                            $disk->putFileAs('images', $file, $filename);

                            $otherVehicleImagePath = 'images/' . $filename;
                        }
                        $groupArrayWithoutNumberedIndex[0]['other_vehicle_image_path'] = env('FTP_ROOT_PATH') . '/' . $otherVehicleImagePath;
                    }
                }

                $updateData['other_guests_data'] = json_encode($groupArrayWithoutNumberedIndex);
            }

            if (!empty($updateData)) {
                $updatedGuestInfo = DB::connection('remote_test')->table('virtualdesigns_erpbookings_guestinfo')
                    ->where('booking_id', '=', $request->guest_booking_id)
                    ->update($updateData);

                if ($updatedGuestInfo) {
                    return response()->json([
                        'success' => true,
                        'message' => "Successfully Updated Guest Details",
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => "Failed To Update Guest Details",
                    ], 500);
                }
            }
            return response()->json([
                'success' => true,
                'message' => "Details already filled in.",
            ]);
        } else {
            if ($request->body_corp_full_names_required == 1) {
                $body_corp_full_names_required = 1;
            } else {
                $body_corp_full_names_required = 0;
            }

            if ($request->body_corp_vehicle_reg_required == 1) {
                $body_corp_vehicle_reg_required = 1;
            } else {
                $body_corp_vehicle_reg_required = 0;
            }

            if ($request->body_corp_id_selfies_required == 1) {
                $body_corp_id_selfies_required = 1;
            } else {
                $body_corp_id_selfies_required = 0;
            }

            if ($request->body_corp_all_guest_contacts_required == 1) {
                $body_corp_all_guest_contacts_required = 1;
            } else {
                $body_corp_all_guest_contacts_required = 0;
            }

            if ($request->body_corp_all_guest_id_img_required == 1) {
                $body_corp_all_guest_id_img_required = 1;
            } else {
                $body_corp_all_guest_id_img_required = 0;
            }

            if ($request->body_corp_main_guest_name_and_phone_number_required == 1) {
                $body_corp_main_guest_name_and_phone_number_required = 1;
            } else {
                $body_corp_main_guest_name_and_phone_number_required = 0;
            }

            if ($request->main_guest_name_phone_number_and_id_number_image_upload_required == 1) {
                $main_guest_name_phone_number_and_id_number_image_upload_required = 1;
            } else {
                $main_guest_name_phone_number_and_id_number_image_upload_required = 0;
            }


            if ($request->body_corp_to_send == 1) {
                $body_corp_to_send = 1;
            } else {
                $body_corp_to_send = 0;
            }

            $body_corp_emails = array();
            $emails_inc = 0;
            while ($emails_inc <= $request->body_corp_emails_count) {
                $body_corp_email = $request->input('body_corp_email_' . $emails_inc);
                array_push($body_corp_emails, $body_corp_email);
                $emails_inc = $emails_inc + 1;
            }

            if ($body_corp_id != null && $body_corp_id != "") {
                DB::connection('remote_test')->table('virtualdesigns_bodycorp_bodycorp')
                    ->where('id', '=', $body_corp_id)
                    ->where('deleted_at', '=', null)
                    ->update([
                        "rule_name" => $request->rule_name,
                        "body_corp_name" => $request->body_corp_name,
                        "body_corp_phone" => $request->body_corp_phone,
                        "body_corp_contact_person" => $request->body_corp_contact_person,
                        "body_corp_emails" => json_encode($body_corp_emails),
                        "notes" => $request->body_corp_notes,
                        "body_corp_full_names_required" => $body_corp_full_names_required,
                        "body_corp_to_send" => $body_corp_to_send,
                        //"body_corp_total_guests_required" => $body_corp_total_guests_required,
                        "body_corp_vehicle_reg_required" => $body_corp_vehicle_reg_required,
                        "body_corp_id_selfies_required" => $body_corp_id_selfies_required,
                        "body_corp_all_guest_contacts_required" => $body_corp_all_guest_contacts_required,
                        "body_corp_all_guest_id_img_required" => $body_corp_all_guest_id_img_required,
                        "body_corp_main_guest_name_and_phone_number_required" => $body_corp_main_guest_name_and_phone_number_required,
                        "main_guest_name_phone_number_and_id_number_image_upload_required" => $main_guest_name_phone_number_and_id_number_image_upload_required,
                        "created_at" => date("Y-m-d H:i:s")
                    ]);
                $message_sent = "Guest Checkin Rule Updated Successfully";
            } else {
                $exists = DB::connection('remote_test')->table('virtualdesigns_bodycorp_bodycorp')
                    ->where('virtualdesigns_bodycorp_bodycorp.deleted_at', '=', null)
                    ->where("rule_name", $request->rule_name)->exists();


                if ($request->rule_name !== "" && !$exists) {
                    DB::connection('remote_test')->table('virtualdesigns_bodycorp_bodycorp')
                        ->insert([
                            "rule_name" => $request->rule_name,
                            "body_corp_name" => $request->body_corp_name,
                            "body_corp_phone" => $request->body_corp_phone,
                            "body_corp_contact_person" => $request->body_corp_contact_person,
                            "body_corp_emails" => json_encode($body_corp_emails),
                            "notes" => $request->body_corp_notes,
                            "body_corp_to_send" => $body_corp_to_send,
                            "body_corp_full_names_required" => $body_corp_full_names_required,
                            //"body_corp_total_guests_required" => $body_corp_total_guests_required,
                            "body_corp_vehicle_reg_required" => $body_corp_vehicle_reg_required,
                            "body_corp_id_selfies_required" => $body_corp_id_selfies_required,
                            "body_corp_all_guest_contacts_required" => $body_corp_all_guest_contacts_required,
                            "body_corp_all_guest_id_img_required" => $body_corp_all_guest_id_img_required,
                            "body_corp_main_guest_name_and_phone_number_required" => $body_corp_main_guest_name_and_phone_number_required,
                            "main_guest_name_phone_number_and_id_number_image_upload_required" => $main_guest_name_phone_number_and_id_number_image_upload_required,
                            //"body_corp_guest_eta_required" => $body_corp_guest_eta_required,
                            "created_at" => date("Y-m-d H:i:s")
                        ]);
                    $message_sent = "New Guest Checkin Rule Created";
                } else {
                    $message_sent = "Rule name already exists!";
                }
            }
            $body_corps = DB::connection('remote_test')
                ->table('virtualdesigns_bodycorp_bodycorp')
                ->where('virtualdesigns_bodycorp_bodycorp.deleted_at', '=', null)
                ->get();

            $emails = DB::connection('remote_test')
                ->table('virtualdesigns_bodycorp_emails')
                ->get()
                ->groupBy('bodycorp_id');

            $body_corps = $body_corps->map(function ($corp) use ($emails) {
                $corp->emails = $emails->get($corp->id, collect())->toArray();
                return $corp;
            });
            $success = $message_sent !== "Rule name already exists!";
            return response()->json([
                'success' => $success,
                'message' => $message_sent,
                'data' => [
                    'body_corps' => $body_corps,
                ],
            ], $success ? 200 : 409);
        }
    }
}
