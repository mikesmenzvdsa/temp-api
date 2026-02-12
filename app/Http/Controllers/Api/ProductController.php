<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{

    private function corsJson($data, int $status)
    {
        return response()
            ->json($data, $status)
            ->header('Content-Type', 'application.json')
            ->header('Access-Control-Allow-Origin', '*');
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

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        try {
            $this->assertApiKey($request);

            $body_corps = DB::table('virtualdesigns_bodycorp_bodycorp')
                ->where('deleted_at', '=', null)
                ->get();

            $rules = array(
                'body_corp_to_send',
                'body_corp_full_names_required',
                'body_corp_vehicle_reg_required',
                'body_corp_id_selfies_required',
                'body_corp_all_guest_contacts_required',
                'body_corp_all_guest_id_img_required',
                'body_corp_main_guest_name_and_phone_number_required',
                'main_guest_name_phone_number_and_id_number_image_upload_required'
            );

            return $this->corsJson(["bodycorp" => $body_corps, "rules" => $rules], 200);
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }
            return $this->corsJson(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Product data that can be use to visualized data.
     */
    public function dashboard(Request $request)
    {
        return $this->corsJson("Product Dashboard", 500);
    }

    /**
     * Product data that can be use to visualized data.
     */
    public function storeBodyCorp(Request $request)
    {
        try {

            $this->assertApiKey($request);
            Log::debug($request);

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

            if (isset($request->body_corp_email) && $request->body_corp_email !== "") {
                array_push($body_corp_emails, $request->body_corp_email);
            }
            // while ($emails_inc <= $request->body_corp_emails_count) {
            //     $body_corp_email = $request->input('body_corp_email_' . $emails_inc);
            //     array_push($body_corp_emails, $body_corp_email);
            //     $emails_inc = $emails_inc + 1;
            // }

            $insertBodyCorp = DB::table('virtualdesigns_bodycorp_bodycorp')
                ->insert([
                    "rule_name" => $request->rule_name,
                    "body_corp_name" => $request->body_corp_name,
                    "body_corp_phone" => $request->body_corp_phone,
                    "body_corp_contact_person" => $request->body_corp_contact_person,
                    "body_corp_emails" => json_encode($body_corp_emails),
                    "notes" => $request->notes,
                    "body_corp_to_send" => $body_corp_to_send,
                    "body_corp_full_names_required" => $body_corp_full_names_required,
                    "body_corp_vehicle_reg_required" => $body_corp_vehicle_reg_required,
                    "body_corp_id_selfies_required" => $body_corp_id_selfies_required,
                    "body_corp_all_guest_contacts_required" => $body_corp_all_guest_contacts_required,
                    "body_corp_all_guest_id_img_required" => $body_corp_all_guest_id_img_required,
                    "body_corp_main_guest_name_and_phone_number_required" => $body_corp_main_guest_name_and_phone_number_required,
                    "main_guest_name_phone_number_and_id_number_image_upload_required" => $main_guest_name_phone_number_and_id_number_image_upload_required,
                    "created_at" => date("Y-m-d H:i:s")
                ]);

            return $this->corsJson(["message" => $insertBodyCorp], 200);
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }
            return $this->corsJson(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
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
                            "body_corp_vehicle_reg_required" => $body_corp_vehicle_reg_required,
                            "body_corp_id_selfies_required" => $body_corp_id_selfies_required,
                            "body_corp_all_guest_contacts_required" => $body_corp_all_guest_contacts_required,
                            "body_corp_all_guest_id_img_required" => $body_corp_all_guest_id_img_required,
                            "body_corp_main_guest_name_and_phone_number_required" => $body_corp_main_guest_name_and_phone_number_required,
                            "main_guest_name_phone_number_and_id_number_image_upload_required" => $main_guest_name_phone_number_and_id_number_image_upload_required,
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
            // Redirect Problem that needs to be solved.
            view("pages.product.check-in-rules", ['message_sent' => $message_sent, 'body_corps' => $body_corps]);
            return redirect()->back()->with('message_sent', $message_sent);
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }
            return $this->corsJson(['error' => $e->getMessage(), "message" => "Failed to update Body Corporate By Id"], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        try {
            if ($request->deleted) {
                $delete_body_corps = DB::table('virtualdesigns_bodycorp_bodycorp')
                    ->where('id', '=', $id)
                    ->update(['deleted_at' => date("Y-m-d H:i:s")]);


                $bodycorps = DB::connection('remote_test')
                    ->table('virtualdesigns_bodycorp_bodycorp')
                    ->where('deleted_at', '=', null)
                    ->get();

                $message_sent = "Body Corporate Deleted Succesfully";
                if ($delete_body_corps === 1) {
                    return $this->corsJson(["message" => $message_sent, "deleted" => true, "bodycorps" => $bodycorps], 200);
                }
            }
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }
            return $this->corsJson(['error' => $e->getMessage(), "message" => "Failed to deleted Body Corporate By Id"], 500);
        }
    }
}
