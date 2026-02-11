<?php

namespace App\Http\Controllers\Api;

use App\Booking;
use Carbon\Carbon;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReservationsController extends Controller
{
    /**
     * Display accounts reservations pages when authenticated
     *
     * @param string $page
     * @return \Illuminate\View\View
     */


    public function reservations(string $page): JsonResponse
    {
        if (view()->exists("pages.reservations.{$page}")) {
            Log::debug("view-page");
            $db_connection = DB::connection('remote_test');
            $fourteenDaysLater = Carbon::now()->addDays(14);
            $sevenDaysEarlier = Carbon::now()->subDays(7)->toDateString();
            $thirtyDaysEarlier = Carbon::now()->subDays(30)->toDateString();
            $yesterday = Carbon::now()->subDays()->toDateString();
            $today = Carbon::now()->toDateString();
            $tomorrow = Carbon::now()->addDays(1)->toDateString();
            $thirtyDaysLater = Carbon::now()->subDays(30)->toDateString();

            $user_email = Auth::user()->email;
            $role = Auth::user()->role;

            $bodyCorpDefault = $db_connection->table('virtualdesigns_bodycorp_bodycorp')->where('id', '=', 1)->whereNull('deleted_at')->first();
            $allBodyCorps = $db_connection->table('virtualdesigns_bodycorp_bodycorp')->whereNull('deleted_at')->get();
            $stages = array(1 => "In progress", 2 => "Allocated", 3 => "Completed", 4 => "More Info Needed", 5 => "Awaiting Quote", 6 => "Awaiting Approval", 7 => "Booked In", 8 => "Work in Progress", 9 => "Ignored by Owner", 10 => "Rejected by PM");

            if ($page == 'dashboard') {
                $today_arrival_date = Booking::where('arrival_date', '=', $today)
                    ->where('booking.status', '!=', 1)
                    ->where('booking.deleted_at', '=', null)
                    ->where('booking.quote_confirmed', '=', 1)
                    ->distinct()
                    ->count();

                $today_departure_date = Booking::where('departure_date', '=', $today)
                    ->where('booking.status', '!=', 1)
                    ->where('booking.deleted_at', '=', null)
                    ->where('booking.quote_confirmed', '=', 1)
                    ->count();

                $msc = Booking::join('virtualdesigns_cleans_cleans as cleans', 'cleans.booking_id', '=', 'booking.id')
                    ->where('booking.status', '!=', 1)
                    ->where('booking.deleted_at', '=', null)
                    ->where('booking.quote_confirmed', '=', 1)
                    ->where('clean_type', '=', 'MSA')
                    ->where('clean_date', '=', $today)
                    ->count();
                $today_reservations_count = Booking::whereBetween('date_confirmed', [
                    today()->format('Y-m-d') . ' 00:00:00',
                    today()->format('Y-m-d') . ' 23:59:59',
                ])
                    ->where('booking.status', '!=', 1)
                    ->where('booking.deleted_at', '=', null)
                    ->where('booking.quote_confirmed', '=', 1)
                    ->count();

                $tomorrow_reservations_count = Booking::where('arrival_date', '=', date('Y-m-d', strtotime('+1 day')))
                    ->where('booking.status', '!=', 1)
                    ->where('booking.deleted_at', '=', null)
                    ->where('booking.quote_confirmed', '=', 1)
                    ->count();

                $reported_issues = $db_connection->table('virtualdesigns_reportedissues_issues as issues')
                    ->join('virtualdesigns_properties_properties as property', 'issues.property_id', '=', 'property.id')
                    ->whereNull('issues.deleted_at')
                    ->whereNotNull('issues.date_booked')
                    ->where('issues.date_booked', '<>', '')
                    ->where('issues.date_booked', '=', $today)
                    ->where('issues.description', '<>', '')
                    ->whereNotLike('issues.description', '%test%')
                    ->select('issues.description', 'issues.stage', 'issues.date_booked', 'property.name')->get();
                $reported_issues_in_progress_last_seven_days_count = $db_connection->table('virtualdesigns_reportedissues_issues as issues')
                    ->join('virtualdesigns_properties_properties as property', 'issues.property_id', '=', 'property.id')
                    ->whereNull('issues.deleted_at')
                    ->whereNotNull('issues.date_booked')
                    ->where('issues.date_booked', '<>', '')
                    ->wherebetween('issues.date_booked', [$sevenDaysEarlier, $today])
                    ->where('issues.description', '<>', '')
                    ->whereNotLike('issues.description', '%test%')
                    ->whereNotLike('issues.stage', 1)
                    ->select('issues.description', 'issues.stage', 'issues.date_booked', 'property.name')->get()->count();

                $reported_issues_completed_last_seven_days_count = $db_connection->table('virtualdesigns_reportedissues_issues as issues')
                    ->join('virtualdesigns_properties_properties as property', 'issues.property_id', '=', 'property.id')
                    ->whereNull('issues.deleted_at')
                    ->whereNotNull('issues.date_booked')
                    ->where('issues.date_booked', '<>', '')
                    ->wherebetween('issues.date_booked', [$sevenDaysEarlier, $today])
                    ->where('issues.description', '<>', '')
                    ->whereNotLike('issues.description', '%test%')
                    ->whereNotLike('issues.stage', 3)
                    ->select('issues.description', 'issues.stage', 'issues.date_booked', 'property.name')->get()->count();

                $reported_issues_completed_last_thirty_days_count = $db_connection->table('virtualdesigns_reportedissues_issues as issues')
                    ->join('virtualdesigns_properties_properties as property', 'issues.property_id', '=', 'property.id')
                    ->whereNull('issues.deleted_at')
                    ->whereNotNull('issues.date_booked')
                    ->where('issues.date_booked', '<>', '')
                    ->wherebetween('issues.date_booked', [$thirtyDaysEarlier, $today])
                    ->where('issues.description', '<>', '')
                    ->whereNotLike('issues.description', '%test%')
                    ->whereNotLike('issues.stage', 3)
                    ->select('issues.description', 'issues.stage', 'issues.date_booked', 'property.name')->get()->count();

                $reported_issues_tomorrow = $db_connection->table('virtualdesigns_reportedissues_issues as issues')
                    ->join('virtualdesigns_properties_properties as property', 'issues.property_id', '=', 'property.id')
                    ->whereNull('issues.deleted_at')
                    ->whereNotNull('issues.date_booked')
                    ->where('issues.date_booked', '<>', '')
                    ->where('issues.date_booked', '=', $tomorrow)
                    ->where('issues.description', '<>', '')
                    ->whereNotLike('issues.description', '%test%')
                    ->select('issues.description', 'issues.stage', 'issues.date_booked', 'property.name')->get();

                $todays_booking_completed = $db_connection
                    ->table('virtualdesigns_erpbookings_erpbookings as booking')
                    ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                    ->Leftjoin('virtualdesigns_bodycorp_bodycorp as bodycorp', 'prop.bodycorp_id', '=', 'bodycorp.id')
                    ->Leftjoin('virtualdesigns_erpbookings_guestinfo as guestinfo', 'guestinfo.booking_id', '=', 'booking.id')
                    ->Leftjoin('virtualdesigns_changes_changes as changes', 'changes.record_id', '=', 'booking.id')
                    ->Leftjoin('users', 'users.id', '=', 'booking.made_by')
                    ->where('booking.status', '!=', 1)
                    ->where('booking.deleted_at', '=', null)
                    ->where('booking.quote_confirmed', '=', 1)
                    ->where('booking.arrival_date',  '=', $today)
                    ->where('guestinfo.completed', '=', 1)
                    ->where('guestinfo.mail_sent_to_body_corp', '=', 1)
                    ->when(($role->id !== 1 && $role->id !== 2), function ($query) use ($user_email) {
                        $query->where('email', $user_email);
                    })
                    ->select(
                        "booking.arrival_date",
                        "booking.client_name",
                        "booking.booking_ref",
                        "guestinfo.completed"
                    )
                    ->distinct()
                    ->get();

                $last_seven_days_booking_completed = $db_connection
                    ->table('virtualdesigns_erpbookings_erpbookings as booking')
                    ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                    ->Leftjoin('virtualdesigns_bodycorp_bodycorp as bodycorp', 'prop.bodycorp_id', '=', 'bodycorp.id')
                    ->Leftjoin('virtualdesigns_erpbookings_guestinfo as guestinfo', 'guestinfo.booking_id', '=', 'booking.id')
                    ->Leftjoin('virtualdesigns_changes_changes as changes', 'changes.record_id', '=', 'booking.id')
                    ->Leftjoin('users', 'users.id', '=', 'booking.made_by')
                    ->where('booking.status', '!=', 1)
                    ->where('booking.deleted_at', '=', null)
                    ->where('booking.quote_confirmed', '=', 1)
                    ->whereBetween('booking.arrival_date',   [$sevenDaysEarlier, $today])
                    ->where('guestinfo.completed', '=', 1)
                    ->where('guestinfo.mail_sent_to_body_corp', '=', 1)
                    ->when(($role->id !== 1 && $role->id !== 2), function ($query) use ($user_email) {
                        $query->where('email', $user_email);
                    })
                    ->select(
                        "booking.arrival_date",
                        "booking.booking_ref",
                        "booking.client_name",
                        "guestinfo.completed"
                    )
                    ->distinct()
                    ->get()
                    ->count();

                $todays_booking_incompleted = $db_connection
                    ->table('virtualdesigns_erpbookings_erpbookings as booking')
                    ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                    ->Leftjoin('virtualdesigns_bodycorp_bodycorp as bodycorp', 'prop.bodycorp_id', '=', 'bodycorp.id')
                    ->Leftjoin('virtualdesigns_erpbookings_guestinfo as guestinfo', 'guestinfo.booking_id', '=', 'booking.id')
                    ->Leftjoin('virtualdesigns_changes_changes as changes', 'changes.record_id', '=', 'booking.id')
                    ->Leftjoin('users', 'users.id', '=', 'booking.made_by')
                    ->where('booking.status', '!=', 1)
                    ->where('booking.deleted_at', '=', null)
                    ->where('booking.quote_confirmed', '=', 1)
                    ->where('booking.arrival_date',  '=', $today)
                    ->where(function ($q) {
                        $q->whereNull('guestinfo.completed')
                            ->orWhere('guestinfo.completed', 0);
                    })
                    ->where(function ($q) {
                        $q->whereNull('guestinfo.mail_sent_to_body_corp')
                            ->orWhere('guestinfo.mail_sent_to_body_corp', 0);
                    })
                    ->when(($role->id !== 1 && $role->id !== 2), function ($query) use ($user_email) {
                        $query->where('email', $user_email);
                    })
                    ->select(
                        "booking.arrival_date",
                        "booking.client_name",
                        "booking.booking_ref",
                        "guestinfo.completed"
                    )
                    ->distinct()
                    ->get();

                $last_seven_days_booking_incompleted = $db_connection
                    ->table('virtualdesigns_erpbookings_erpbookings as booking')
                    ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                    ->Leftjoin('virtualdesigns_bodycorp_bodycorp as bodycorp', 'prop.bodycorp_id', '=', 'bodycorp.id')
                    ->Leftjoin('virtualdesigns_erpbookings_guestinfo as guestinfo', 'guestinfo.booking_id', '=', 'booking.id')
                    ->Leftjoin('virtualdesigns_changes_changes as changes', 'changes.record_id', '=', 'booking.id')
                    ->Leftjoin('users', 'users.id', '=', 'booking.made_by')
                    ->where('booking.status', '!=', 1)
                    ->where('booking.deleted_at', '=', null)
                    ->where('booking.quote_confirmed', '=', 1)
                    ->whereBetween('booking.arrival_date',   [$sevenDaysEarlier, $today])
                    ->where(function ($q) {
                        $q->whereNull('guestinfo.completed')
                            ->orWhere('guestinfo.completed', 0);
                    })
                    ->where(function ($q) {
                        $q->whereNull('guestinfo.mail_sent_to_body_corp')
                            ->orWhere('guestinfo.mail_sent_to_body_corp', 0);
                    })
                    ->when(($role->id !== 1 && $role->id !== 2), function ($query) use ($user_email) {
                        $query->where('email', $user_email);
                    })
                    ->select(
                        "booking.arrival_date",
                        "booking.client_name",
                        "booking.booking_ref",
                        "guestinfo.completed"
                    )
                    ->distinct()
                    ->get()
                    ->count();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'page' => $page,
                        'title' => 'Dashboard',
                        'today_arrival_date' => $today_arrival_date,
                        'today_departure_date' => $today_departure_date,
                        'msc' => $msc,
                        'today_reservations_count' => $today_reservations_count,
                        'tomorrow_reservations_count' => $tomorrow_reservations_count,
                        'roles' => $role,
                        'reported_issues' => $reported_issues,
                        'stages' => $stages,
                        'reported_issues_tomorrow' => $reported_issues_tomorrow,
                        'todays_booking_completed' => $todays_booking_completed,
                        'todays_booking_incompleted' => $todays_booking_incompleted,
                        'last_seven_days_booking_completed' => $last_seven_days_booking_completed,
                        'last_seven_days_booking_incompleted' => $last_seven_days_booking_incompleted,
                        'reported_issues_in_progress_last_seven_days_count' => $reported_issues_in_progress_last_seven_days_count,
                        'reported_issues_completed_last_seven_days_count' => $reported_issues_completed_last_seven_days_count,
                        'reported_issues_completed_last_thirty_days_count' => $reported_issues_completed_last_thirty_days_count,
                    ],
                ]);
            } elseif ($page == 'collect-guest-details') {

                $bookings = $db_connection
                    ->table('virtualdesigns_erpbookings_erpbookings as booking')
                    ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                    ->Leftjoin('virtualdesigns_bodycorp_bodycorp as bodycorp', 'bodycorp.id', '=', 'prop.bodycorp_id')
                    ->Leftjoin('virtualdesigns_erpbookings_guestinfo as guestinfo', 'guestinfo.booking_id', '=', 'booking.id')
                    ->Leftjoin('virtualdesigns_changes_changes as changes', 'changes.record_id', '=', 'booking.id')
                    ->Leftjoin('users', 'users.id', '=', 'booking.made_by')
                    ->where('booking.status', '!=', 1)
                    ->where('booking.deleted_at', '=', null)
                    ->where('booking.quote_confirmed', '=', 1)
                    ->when(($role->id !== 1 && $role->id !== 2), function ($query) use ($user_email) {
                        $query->where('email', $user_email);
                    })
                    ->where(function ($q) {
                        $q->whereNull('guestinfo.completed')
                            ->orWhere('guestinfo.completed', 0);
                    })
                    ->whereBetween('booking.arrival_date', [$today, $fourteenDaysLater])
                    ->selectRaw(
                        "prop.name as prop_name,
                        prop.id as prop_id,
                        prop.bodycorp_id as prop_bodycorp_id,
                        booking.arrival_date,
                        booking.departure_date,
                        bodycorp.*,
                        guestinfo.id as guestinfo_id,
                        guestinfo.guest_name as guestinfo_guest_name,
                        guestinfo.guest_id_no as guestinfo_guest_id_no,
                        guestinfo.guest_contact as guestinfo_guest_contact,
                        guestinfo.guest_no as guestinfo_guest_no,
                        guestinfo.guest_email as guestinfo_guest_email,
                        guestinfo.eta as guestinfo_eta,
                        guestinfo.etd as guestinfo_etd,
                        guestinfo.vehicle_reg as guestinfo_vehicle_reg,
                        guestinfo.completed as guestinfo_completed,
                        guestinfo.booking_id as guestinfo_booking_id,
                        guestinfo.guest_alternative_email_address as guestinfo_guest_alternative_email_address,
                        guestinfo.guest_id as guestinfo_guest_id,
                        guestinfo.vehicle_image_path as guestinfo_vehicle_image_path,
                        guestinfo.selfie_image_path as guestinfo_selfie_image_path,
                        guestinfo.other_guests_data as guestinfo_other_guests_data,
                        prop.capacity,
                        booking.client_name,
                        booking.client_phone,
                        booking.client_email,
                        booking.booking_ref,
                        booking.guest_id_no,
                        booking.id as booking_id,
                        booking.no_guests,
                        users.name as sales_person,
                        guestinfo.mail_sent_to_body_corp as mail_sent,
                        guestinfo.available_vehicle_status,
                        JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', changes.id,
                                'user_id', changes.user_id,
                                'user_name', changes.user_name,
                                'record_id', changes.record_id,
                                'field', changes.field
                            )
                        ) AS changes
                        "
                    )
                    ->groupBy(
                        'booking.id',
                        'prop.id',
                        'bodycorp.id',
                        'guestinfo.id'
                    )
                    ->get();

                // First we want to get all the virtualdesigns_properties_properties that does have a booking id, we will be filtering all the rows that has no ids
                $propertiesWithIds = $db_connection->table('virtualdesigns_properties_properties')
                    ->whereNotNull('bodycorp_id')
                    ->where('bodycorp_id', '!=', 0)->get();

                $prop_recs = $db_connection
                    ->table('virtualdesigns_properties_properties')
                    ->where('deleted_at', '=', null)
                    ->orderBy('name')
                    ->get();
                $selected_date_properties = $bookings->pluck('prop_name', 'prop_id')->unique()->sort();
                $uniqueBookingRefs = $bookings->pluck('booking_ref')->unique()->sort()->values()->toArray();
                $uniqueGuestNames = $bookings->pluck('client_name')->unique()->sort()->values()->toArray();
                $uniqueBodyCorps = $bookings->map(function ($item) {
                    if (!is_null($item->body_corp_name) && !is_null($item->id)) {
                        return [
                            'name' => $item->body_corp_name,
                            'id'   => $item->id,
                        ];
                    }
                })->filter()->unique()->values();

                $defaultCorp = $bodyCorpDefault;

                if (isset($message_sent)) {
                    return response()->json([
                        'success' => true,
                        'message' => $message_sent,
                        'data' => [
                            'page' => $page,
                            'title' => 'Collect Guest Details',
                            'bookings' => $bookings,
                            'prop_recs' => $prop_recs,
                            'uniqueBookingRefs' => $uniqueBookingRefs,
                            'uniqueGuestNames' => $uniqueGuestNames,
                            'uniqueBodyCorps' => $uniqueBodyCorps,
                            'bodyCorpDefault' => $bodyCorpDefault,
                            'date' => date('Y-m-d'),
                            'selected_date_properties' => $selected_date_properties,
                            'default_bodycorp' => $defaultCorp,
                        ],
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'page' => $page,
                        'title' => 'Collect Guest Details',
                        'bookings' => $bookings,
                        'prop_recs' => $prop_recs,
                        'uniqueBookingRefs' => $uniqueBookingRefs,
                        'uniqueGuestNames' => $uniqueGuestNames,
                        'uniqueBodyCorps' => $uniqueBodyCorps,
                        'bodyCorpDefault' => $bodyCorpDefault,
                        'date' => date('Y-m-d'),
                        'selected_date_properties' => $selected_date_properties,
                        'default_bodycorp' => $defaultCorp,
                    ],
                ]);
            } elseif ($page == 'guest-details-collected') {
                Log::debug('guest-details-collected');

                $bookingsCompleted = $db_connection
                    ->table('virtualdesigns_erpbookings_erpbookings as booking')
                    ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                    ->Leftjoin('virtualdesigns_bodycorp_bodycorp as bodycorp', 'bodycorp.id', '=', 'prop.bodycorp_id')
                    ->Leftjoin('virtualdesigns_erpbookings_guestinfo as guestinfo', 'guestinfo.booking_id', '=', 'booking.id')
                    ->Leftjoin('virtualdesigns_changes_changes as changes', 'changes.record_id', '=', 'booking.id')
                    ->Leftjoin('users', 'users.id', '=', 'booking.made_by')
                    ->where('booking.status', '!=', 1)
                    ->where('booking.deleted_at', '=', null)
                    ->where('booking.quote_confirmed', '=', 1)
                    ->where('booking.arrival_date', '=', $today)
                    ->where('guestinfo.completed', '=', 1)
                    ->when(($role->id !== 1 && $role->id !== 2), function ($query) use ($user_email) {
                        $query->where('email', $user_email);
                    })
                    ->where(function ($q) {
                        $q->whereNull('guestinfo.mail_sent_to_body_corp')
                            ->orWhere('guestinfo.mail_sent_to_body_corp', 0);
                    })
                    ->selectRaw(
                        "prop.name as prop_name,
                        prop.id as prop_id,
                        booking.arrival_date,
                        booking.departure_date,
                        bodycorp.*,
                        guestinfo.id as guestinfo_id,
                        guestinfo.guest_name as guestinfo_guest_name,
                        guestinfo.guest_id_no as guestinfo_guest_id_no,
                        guestinfo.guest_contact as guestinfo_guest_contact,
                        guestinfo.guest_email as guestinfo_guest_email,
                        guestinfo.guest_no as guestinfo_guest_no,
                        guestinfo.eta as guestinfo_eta,
                        guestinfo.etd as guestinfo_etd,
                        guestinfo.vehicle_reg as guestinfo_vehicle_reg,
                        guestinfo.completed as guestinfo_completed,
                        guestinfo.booking_id as guestinfo_booking_id,
                        guestinfo.guest_alternative_email_address as guestinfo_guest_alternative_email_address,
                        guestinfo.guest_id as guestinfo_guest_id,
                        guestinfo.vehicle_image_path as guestinfo_vehicle_image_path,
                        guestinfo.selfie_image_path,
                        guestinfo.other_guests_data,
                        prop.capacity,
                        booking.client_name,
                        booking.client_phone,
                        booking.client_email,
                        booking.booking_ref,
                        booking.guest_id_no,
                        booking.id as booking_id,
                        booking.property_id,
                        booking.no_guests,
                        users.name as sales_person,
                        guestinfo.mail_sent_to_body_corp as mail_sent,
                        guestinfo.available_vehicle_status,
                        JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', changes.id,
                                'user_id', changes.user_id,
                                'user_name', changes.user_name,
                                'record_id', changes.record_id,
                                'field', changes.field
                            )
                        ) AS changes"
                    )
                    ->groupBy(
                        'booking.id',
                        'prop.id',
                        'bodycorp.id',
                        'guestinfo.id'
                    )
                    ->get();

                // First we want to get all the virtualdesigns_properties_properties that does have a booking id, we will be filtering all the rows that has no ids
                $propertiesWithIds = $db_connection->table('virtualdesigns_properties_properties')
                    ->whereNotNull('bodycorp_id')
                    ->where('bodycorp_id', '!=', 0)->get();

                // We got all the properties that has bodycorps 
                $uniqueBodyCorp = $propertiesWithIds->pluck('bodycorp_id')->unique()->sort()->values()->toArray();

                $prop_recs = $db_connection
                    ->table('virtualdesigns_properties_properties')
                    ->where('deleted_at', '=', null)
                    ->orderBy('name')
                    ->get();
                $uniqueBookingRefs = $bookingsCompleted->pluck('booking_ref')->unique()->sort()->values()->toArray();
                $uniqueGuestNames = $bookingsCompleted->pluck('client_name')->unique()->sort()->values()->toArray();
                $uniqueBodyCorps = $bookingsCompleted->map(function ($item) {
                    if (!is_null($item->body_corp_name) && !is_null($item->id)) {
                        return [
                            'name' => $item->body_corp_name,
                            'id'   => $item->id,
                        ];
                    }
                })->filter()->unique()->values();


                $defaultCorp = $bodyCorpDefault;

                return response()->json([
                    'success' => true,
                    'data' => [
                        'page' => $page,
                        'title' => 'Guest Details Collected',
                        'bookings' => $bookingsCompleted,
                        'prop_recs' => $prop_recs,
                        'uniqueBookingRefs' => $uniqueBookingRefs,
                        'uniqueGuestNames' => $uniqueGuestNames,
                        'uniqueBodyCorps' => $uniqueBodyCorps,
                        'bodyCorpDefault' => $bodyCorpDefault,
                        'date' => $today,
                        'default_bodycorp' => $defaultCorp,
                        'allBodyCorps' => $allBodyCorps,
                    ],
                ]);
            } elseif ($page == 'sent-to-body-corp') {
                Log::debug('sent-to-body-corp');

                if (isset($_GET['send'])) {
                    if ($_GET['send'] == 1) {
                        $message_sent = "Message Sent Successfully";
                    }
                }

                $allbookings = $db_connection
                    ->table('virtualdesigns_erpbookings_erpbookings as booking')
                    ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                    ->Leftjoin('virtualdesigns_bodycorp_bodycorp as bodycorp', 'prop.bodycorp_id', '=', 'bodycorp.id')
                    ->Leftjoin('virtualdesigns_erpbookings_guestinfo as guestinfo', 'guestinfo.booking_id', '=', 'booking.id')
                    ->Leftjoin('virtualdesigns_changes_changes as changes', 'changes.record_id', '=', 'booking.id')
                    ->Leftjoin('users', 'users.id', '=', 'booking.made_by')
                    ->where('booking.status', '!=', 1)
                    ->where('booking.deleted_at', '=', null)
                    ->where('booking.quote_confirmed', '=', 1)
                    ->where('booking.arrival_date', '=', $today)
                    ->where('guestinfo.completed', '=', 1)
                    ->where('guestinfo.mail_sent_to_body_corp', '=', 1)
                    ->when(($role->id !== 1 && $role->id !== 2), function ($query) use ($user_email) {
                        $query->where('email', $user_email);
                    })
                    ->selectRaw(
                        "
                        prop.name as prop_name,
                        booking.arrival_date,
                        booking.departure_date,
                        bodycorp.*,
                        booking.client_name,
                        booking.client_phone,
                        booking.client_email,
                        booking.booking_ref,
                        booking.id as booking_id,
                        booking.property_id,
                        booking.no_guests,
                        users.name as sales_person,
                        guestinfo.id as guest_info_id,
                        guestinfo.guest_name,
                        guestinfo.guest_id_no,
                        guestinfo.guest_contact,
                        guestinfo.guest_email,
                        guestinfo.guest_no,
                        guestinfo.vehicle_reg,
                        guestinfo.eta,
                        guestinfo.etd,
                        guestinfo.booking_id as guest_info_booking_id,
                        guestinfo.guest_alternative_email_address,
                        guestinfo.guest_id,
                        guestinfo.completed,
                        guestinfo.vehicle_image_path,
                        guestinfo.other_guests_data,
                        guestinfo.selfie_image_path,
                        guestinfo.mail_sent_to_body_corp,
                        guestinfo.available_vehicle_status,
                        JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', changes.id,
                                'user_id', changes.user_id,
                                'user_name', changes.user_name,
                                'record_id', changes.record_id,
                                'field', changes.field
                            )
                        ) AS changes"
                    )
                    ->groupBy(
                        'booking.id',
                        'prop.id',
                        'bodycorp.id',
                        'guestinfo.id'
                    )
                    ->get();
                $prop_recs = $db_connection
                    ->table('virtualdesigns_properties_properties')
                    ->where('deleted_at', '=', null)
                    ->orderBy('name')
                    ->get();
                $uniqueBookingRefs = $allbookings->pluck('booking_ref')->unique()->sort()->values()->toArray();
                $uniqueGuestNames = $allbookings->pluck('client_name')->unique()->sort()->values()->toArray();
                $uniqueBodyCorps = $allbookings->map(function ($item) {
                    if (!is_null($item->body_corp_name) && !is_null($item->id)) {

                        return [
                            'name' => $item->body_corp_name,
                            'id'   => $item->id,
                        ];
                    }
                })->filter()->unique()->values();
                $defaultCorp = $bodyCorpDefault;
                if (isset($message_sent)) {
                    return response()->json([
                        'success' => true,
                        'message' => $message_sent,
                        'data' => [
                            'page' => $page,
                            'title' => 'Sent To Body Corporate',
                            'bookings' => $allbookings,
                            'prop_recs' => $prop_recs,
                            'uniqueBookingRefs' => $uniqueBookingRefs,
                            'uniqueGuestNames' => $uniqueGuestNames,
                            'uniqueBodyCorps' => $uniqueBodyCorps,
                            'bodyCorpDefault' => $bodyCorpDefault,
                            'default_bodycorp' => $defaultCorp,
                            'allBodyCorps' => $allBodyCorps,
                        ],
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'page' => $page,
                        'title' => 'Sent To Body Corporate',
                        'bookings' => $allbookings,
                        'prop_recs' => $prop_recs,
                        'uniqueBookingRefs' => $uniqueBookingRefs,
                        'uniqueGuestNames' => $uniqueGuestNames,
                        'uniqueBodyCorps' => $uniqueBodyCorps,
                        'bodyCorpDefault' => $bodyCorpDefault,
                        'default_bodycorp' => $defaultCorp,
                        'allBodyCorps' => $allBodyCorps,
                    ],
                ]);
            } elseif ($page == 'past-completed') {
                Log::debug('past-completed');

                if (isset($_GET['send'])) {
                    if ($_GET['send'] == 1) {
                        $message_sent = "Message Sent Successfully";
                    }
                }

                $allbookings = $db_connection
                    ->table('virtualdesigns_erpbookings_erpbookings as booking')
                    ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                    ->Leftjoin('virtualdesigns_bodycorp_bodycorp as bodycorp', 'prop.bodycorp_id', '=', 'bodycorp.id')
                    ->Leftjoin('virtualdesigns_erpbookings_guestinfo as guest_info', 'guest_info.booking_id', '=', 'booking.id')
                    ->Leftjoin('virtualdesigns_changes_changes as changes', 'changes.record_id', '=', 'booking.id')
                    ->Leftjoin('users', 'users.id', '=', 'booking.made_by')
                    ->where('booking.status', '!=', 1)
                    ->where('booking.deleted_at', '=', null)
                    ->where('booking.quote_confirmed', '=', 1)
                    ->whereBetween('booking.arrival_date',  [$thirtyDaysLater, $yesterday])
                    ->where('guest_info.completed', '=', 1)
                    ->where('guest_info.mail_sent_to_body_corp', '=', 1)
                    ->when(($role->id !== 1 && $role->id !== 2), function ($query) use ($user_email) {
                        $query->where('email', $user_email);
                    })
                    ->selectRaw(
                        "
                        prop.name as prop_name,
                        booking.arrival_date,
                        booking.departure_date,
                        bodycorp.*,
                        booking.client_name,
                        booking.client_phone,
                        booking.client_email,
                        booking.booking_ref,
                        booking.id as booking_id,
                        booking.no_guests,
                        users.name as sales_person,
                        guest_info.id as guest_info_id,
                        guest_info.guest_name,
                        guest_info.guest_id_no,
                        guest_info.guest_contact,
                        guest_info.guest_email,
                        guest_info.guest_no,
                        guest_info.vehicle_reg,
                        guest_info.eta,
                        guest_info.etd,
                        guest_info.booking_id as guest_info_booking_id,
                        guest_info.guest_alternative_email_address,
                        guest_info.guest_id,
                        guest_info.completed,
                        guest_info.vehicle_image_path,
                        guest_info.other_guests_data,
                        guest_info.selfie_image_path,
                        guest_info.mail_sent_to_body_corp,
                        guest_info.available_vehicle_status,
                        JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', changes.id,
                                'user_id', changes.user_id,
                                'user_name', changes.user_name,
                                'record_id', changes.record_id,
                                'field', changes.field
                            )
                        ) AS changes"
                    )
                    ->groupBy(
                        'booking.id',
                        'prop.id',
                        'bodycorp.id',
                        'guest_info.id'
                    )
                    ->orderBy('booking.arrival_date', 'ASC')
                    ->get();

                $prop_recs = $db_connection
                    ->table('virtualdesigns_properties_properties')
                    ->where('deleted_at', '=', null)
                    ->orderBy('name')
                    ->get();
                $uniqueBookingRefs = $allbookings->pluck('booking_ref')->unique()->sort()->values()->toArray();
                $uniqueGuestNames = $allbookings->pluck('client_name')->unique()->sort()->values()->toArray();
                $uniqueBodyCorps = $allbookings->map(function ($item) {
                    if (!is_null($item->body_corp_name) && !is_null($item->id)) {

                        return [
                            'name' => $item->body_corp_name,
                            'id'   => $item->id,
                        ];
                    }
                })->filter()->unique()->values();

                $defaultCorp = $bodyCorpDefault;

                if (isset($message_sent)) {
                    return response()->json([
                        'success' => true,
                        'message' => $message_sent,
                        'data' => [
                            'page' => $page,
                            'title' => 'Past Completed',
                            'bookings' => $allbookings,
                            'prop_recs' => $prop_recs,
                            'uniqueBookingRefs' => $uniqueBookingRefs,
                            'uniqueGuestNames' => $uniqueGuestNames,
                            'uniqueBodyCorps' => $uniqueBodyCorps,
                            'bodyCorpDefault' => $bodyCorpDefault,
                            'default_bodycorp' => $defaultCorp,
                            'allBodyCorps' => $allBodyCorps,
                            'thirtyDaysLater' => $thirtyDaysLater,
                            'yesterday' => $yesterday,
                        ],
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'data' => [
                        'page' => $page,
                        'title' => 'Past Completed',
                        'bookings' => $allbookings,
                        'prop_recs' => $prop_recs,
                        'uniqueBookingRefs' => $uniqueBookingRefs,
                        'uniqueGuestNames' => $uniqueGuestNames,
                        'uniqueBodyCorps' => $uniqueBodyCorps,
                        'bodyCorpDefault' => $bodyCorpDefault,
                        'default_bodycorp' => $defaultCorp,
                        'allBodyCorps' => $allBodyCorps,
                        'thirtyDaysLater' => $thirtyDaysLater,
                        'yesterday' => $yesterday,
                    ],
                ]);
            } elseif ($page == 'past-incompleted') {
                Log::debug('past-incompleted');

                $allbookings = $db_connection
                    ->table('virtualdesigns_erpbookings_erpbookings as booking')
                    ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                    ->Leftjoin('virtualdesigns_bodycorp_bodycorp as bodycorp', 'bodycorp.id', '=', 'prop.bodycorp_id')
                    ->Leftjoin('virtualdesigns_erpbookings_guestinfo as guestinfo', 'guestinfo.booking_id', '=', 'booking.id')
                    ->Leftjoin('virtualdesigns_changes_changes as changes', 'changes.record_id', '=', 'booking.id')
                    ->Leftjoin('users', 'users.id', '=', 'booking.made_by')
                    ->where('booking.status', '!=', 1)
                    ->where('booking.deleted_at', '=', null)
                    ->where('booking.quote_confirmed', '=', 1)
                    ->whereBetween('booking.arrival_date',  [$thirtyDaysLater, $yesterday])
                    ->when(($role->id !== 1 && $role->id !== 2), function ($query) use ($user_email) {
                        $query->where('email', $user_email);
                    })
                    ->where(function ($q) {
                        $q->whereNull('guestinfo.completed')
                            ->orWhere('guestinfo.completed', 0);
                    })
                    ->where(function ($q) {
                        $q->whereNull('guestinfo.mail_sent_to_body_corp')
                            ->orWhere('guestinfo.mail_sent_to_body_corp', 0);
                    })
                    ->selectRaw(
                        "
                        prop.name as prop_name,
                        booking.arrival_date,
                        booking.departure_date,
                        bodycorp.*,
                        booking.client_name,
                        booking.client_phone,
                        booking.client_email,
                        booking.booking_ref,
                        booking.id as booking_id,
                        booking.no_guests,
                        users.name as sales_person,
                        guestinfo.id as guest_info_id,
                        guestinfo.guest_name,
                        guestinfo.guest_id_no,
                        guestinfo.guest_contact,
                        guestinfo.guest_email,
                        guestinfo.guest_no,
                        guestinfo.eta,
                        guestinfo.etd,
                        guestinfo.booking_id as guest_info_booking_id,
                        guestinfo.guest_alternative_email_address,
                        guestinfo.guest_id,
                        guestinfo.completed as completed,
                        guestinfo.vehicle_reg,
                        guestinfo.vehicle_image_path,
                        guestinfo.other_guests_data,
                        guestinfo.selfie_image_path,
                        guestinfo.mail_sent_to_body_corp,
                        guestinfo.available_vehicle_status,
                        JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', changes.id,
                                'user_id', changes.user_id,
                                'user_name', changes.user_name,
                                'record_id', changes.record_id,
                                'field', changes.field
                            )
                        ) AS changes"
                    )
                    ->groupBy(
                        'booking.id',
                        'prop.id',
                        'bodycorp.id',
                        'guestinfo.id'
                    )->paginate(10);

                $prop_recs = $db_connection
                    ->table('virtualdesigns_properties_properties')
                    ->where('deleted_at', '=', null)
                    ->orderBy('name')
                    ->get();
                $uniqueBookingRefs = $allbookings->pluck('booking_ref')->unique()->sort()->values()->toArray();
                $uniqueGuestNames = $allbookings->pluck('client_name')->unique()->sort()->values()->toArray();
                $uniqueBodyCorps = $allbookings->map(function ($item) {
                    if (!is_null($item->body_corp_name) && !is_null($item->id)) {

                        return [
                            'name' => $item->body_corp_name,
                            'id'   => $item->id,
                        ];
                    }
                })->filter()->unique()->values();

                $defaultCorp = $bodyCorpDefault;

                return response()->json([
                    'success' => true,
                    'data' => [
                        'page' => $page,
                        'title' => 'Past Incompleted',
                        'bookings' => $allbookings,
                        'prop_recs' => $prop_recs,
                        'uniqueBookingRefs' => $uniqueBookingRefs,
                        'uniqueGuestNames' => $uniqueGuestNames,
                        'uniqueBodyCorps' => $uniqueBodyCorps,
                        'bodyCorpDefault' => $bodyCorpDefault,
                        'default_bodycorp' => $defaultCorp,
                        'allBodyCorps' => $allBodyCorps,
                        'thirtyDaysLater' => $thirtyDaysLater,
                        'yesterday' => $yesterday,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'page' => $page,
                    ],
                ]);
            }
        }

        return response()->json(['success' => false, 'message' => 'Page not found.'], 404);
    }

    public function updatereservations(Request $request): JsonResponse
    {
        Log::debug('reservations-start');
        Log::debug($request->all());
        Log::debug('reservations-end');

        $user_id = Auth::user()->id;
        $user_name = Auth::user()->name;
        $user_email = Auth::user()->email;
        $role = Auth::user()->role;

        $routeName = $request->route()->getName();

        $guest_sent_reg = $request->all();
        $formCaptured = "Guest";

        // This query is to check the previous details that was filled in
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
                "mail_sent_to_body_corp",
                "selfie_image_path",
                "available_vehicle_status",
            )->get();

        $futureDate = "";

        if (isset($request->guest_booking)) {
            $futureDate = json_decode($request->guest_booking, true);
        }

        $getBookingForComparison = DB::connection('remote_test')
            ->table('virtualdesigns_erpbookings_erpbookings as booking')
            ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
            ->Leftjoin('virtualdesigns_bodycorp_bodycorp as body_corp', 'body_corp.id', '=', 'prop.bodycorp_id')
            ->Leftjoin('users', 'users.id', '=', 'booking.made_by')
            ->where('booking.status', '!=', 1)
            ->where('booking.deleted_at', '=', null)
            ->where('booking.quote_confirmed', '=', 1);
        if ($futureDate !== "" && $futureDate['arrival_date'] > date('Y-m-d')) {
            $getBookingForComparison = $getBookingForComparison->where('booking.arrival_date', '=', $futureDate['arrival_date']);
        } else {
            $getBookingForComparison = $getBookingForComparison->where('booking.arrival_date', '=', date('Y-m-d'));
        }
        $getBookingForComparison = $getBookingForComparison->where('booking.id', '=', $request->guest_booking_id)
            ->select(
                'prop.name as prop_name',
                'prop.id as prop_id',
                'booking.arrival_date',
                'booking.departure_date',
                'body_corp.*',
                'prop.capacity',
                'booking.client_name',
                'booking.client_phone',
                'booking.client_email',
                'booking.booking_ref',
                'booking.guest_id_no',
                'booking.id as booking_id',
                'booking.no_guests',
                'users.name as sales_person',
            )
            ->get();

        // If getOldGuestInfo does not exist in the Guestinfo table, we set the Guest default to Guest,
        if (count($getOldGuestInfo) > 0) {
            $formCaptured = $user_name;
        }

        Log::DEBUG("The Person that Captured the form is" . $formCaptured);

        $allBodyCorps = DB::connection('remote_test')->table('virtualdesigns_bodycorp_bodycorp')->whereNull('deleted_at')->get();

        if (isset($request->is_filter)) {
            Log::debug('Filter Is Set');
            $filter_values = array();
            $bookings = DB::connection('remote_test')
                ->table('virtualdesigns_erpbookings_erpbookings as booking')
                ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                ->Leftjoin('virtualdesigns_bodycorp_bodycorp as body_corp', 'body_corp.id', '=', 'prop.bodycorp_id')
                ->Leftjoin('virtualdesigns_erpbookings_guestinfo as guestinfo', 'guestinfo.booking_id', '=', 'booking.id')
                ->Leftjoin('virtualdesigns_changes_changes as changes', 'changes.record_id', '=', 'booking.id')
                ->Leftjoin('users', 'users.id', '=', 'booking.made_by')
                ->where('booking.status', '!=', 1)
                ->where('booking.deleted_at', '=', null)
                ->where('booking.quote_confirmed', '=', 1)
                ->when(($role->id !== 1 && $role->id !== 2), function ($query) use ($user_email) {
                    $query->where('email', $user_email);
                });

            if (isset($request->filter_arrival_date)) {
                if ($request->filter_arrival_date != "" && $request->filter_arrival_date != null) {
                    $bookings = $bookings->where('booking.arrival_date', '=', date('Y-m-d', strtotime($request->filter_arrival_date)));
                    $filter_values['date'] = $request->filter_arrival_date;
                } else {
                    $bookings = $bookings->where('booking.arrival_date', '=', date('Y-m-d'));
                    $filter_values['date'] = $request->filter_arrival_date;
                }
            } else if (!$request->filled(['filter_start_arrival_date', 'filter_end_arrival_date'])) {
                $bookings = $bookings->where('booking.arrival_date', '=', date('Y-m-d'));
                $filter_values['date'] = "";
            }

            if ($request->filled(['filter_start_arrival_date', 'filter_end_arrival_date'])) {

                $startDate = Carbon::parse($request->filter_start_arrival_date)->startOfDay()->toDateString();
                $endDate   = Carbon::parse($request->filter_end_arrival_date)->endOfDay()->toDateString();

                $bookings->whereBetween('booking.arrival_date', [$startDate, $endDate]);

                $filter_values['start-date'] = $request->filter_start_arrival_date;
                $filter_values['end-date']   = $request->filter_end_arrival_date;
            }

            if (isset($request->filter_prop)) {
                if ($request->filter_prop > 0) {
                    $bookings = $bookings->where('booking.property_id', '=', $request->filter_prop);
                    $filter_values['prop'] = $request->filter_prop;
                }
            } else {
                $filter_values['prop'] = 0;
            }

            if (isset($request->filter_body_corp_name)) {
                if ($request->filter_body_corp_name != '-- Select --') {
                    $getOldGuestInfo1 = DB::connection('remote_test')->table('virtualdesigns_bodycorp_bodycorp')
                        ->where('id', '=', $request->filter_body_corp_name)
                        ->whereNull('deleted_at')
                        ->select(
                            "id",
                            "body_corp_name",
                        )->get();
                    $bookings = $bookings->where('body_corp.id', '=', $request->filter_body_corp_name);
                    $filter_values['body_corp_name'] = $getOldGuestInfo1[0]->body_corp_name;
                    $filter_values['body_corp_id'] = $getOldGuestInfo1[0]->id;
                }
            } else {
                $filter_values['body_corp_name'] = "No result found";
                $filter_values['body_corp_id'] = "No result found";
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

            if (str_contains($routeName, 'collect-guest-details')) {
                $bookings = $bookings->where(function ($q) {
                    $q->whereNull('guestinfo.completed')
                        ->orWhere('guestinfo.completed', 0);
                });
            }
            if (str_contains($routeName, 'guest-details-collected')) {
                $bookings = $bookings->where('guestinfo.completed', '=', 1)
                    ->where(function ($q) {
                        $q->whereNull('guestinfo.mail_sent_to_body_corp')
                            ->orWhere('guestinfo.mail_sent_to_body_corp', 0);
                    });
            }

            if (str_contains($routeName, 'sent-to-body-corp')) {
                $bookings = $bookings->where('guestinfo.completed', '=', 1);
            }
            if (str_contains($routeName, 'sent-to-body-corp')) {
                $bookings = $bookings->where('guestinfo.mail_sent_to_body_corp', '=', 1);
            }

            if (str_contains($routeName, 'past-completed')) {
                $bookings = $bookings->where('guestinfo.completed', '=', 1);
            }
            if (str_contains($routeName, 'past-completed')) {
                $bookings = $bookings->where('guestinfo.mail_sent_to_body_corp', '=', 1);
            }

            if (str_contains($routeName, 'past-incompleted')) {
                $bookings = $bookings->where(function ($q) {
                    $q->whereNull('guestinfo.completed')
                        ->orWhere('guestinfo.completed', 0);
                });
            }

            if (str_contains($routeName, 'past-incompleted')) {
                $bookings = $bookings->where(function ($q) {
                    $q->whereNull('guestinfo.mail_sent_to_body_corp')
                        ->orWhere('guestinfo.mail_sent_to_body_corp', 0);
                });
            }

            $bookings = $bookings->selectRaw(
                "
                prop.name as prop_name,
                booking.arrival_date,
                booking.departure_date,
                body_corp.*,
                booking.client_name,
                booking.client_phone,
                booking.client_email,
                booking.guest_id_no,
                booking.no_guests,
                booking.booking_ref,
                booking.id as booking_id,
                users.name as sales_person,
                prop.capacity,
                prop.id as property_id,
                guestinfo.id as guestinfo_id,
                guestinfo.guest_name as guestinfo_guest_name,
                guestinfo.guest_id_no as guestinfo_guest_id_no,
                guestinfo.guest_contact as guestinfo_guest_contact,
                guestinfo.guest_no,
                guestinfo.eta as guestinfo_eta,
                guestinfo.etd as guestinfo_etd,
                guestinfo.flight_number as guestinfo_flight_number,
                guestinfo.bank_ac_name as guestinfo_bank_ac_name,
                guestinfo.bank_ac_no as guestinfo_bank_ac_no,
                guestinfo.bank_name as guestinfo_bank_name,
                guestinfo.bank_code as guestinfo_bank_code,
                guestinfo.no_smoking as guestinfo_no_smoking,
                guestinfo.noise_policy as guestinfo_noise_policy,
                guestinfo.fair_usage_policy as guestinfo_fair_usage_policy,
                guestinfo.breakage_policy as guestinfo_breakage_policy,
                guestinfo.terms_conditions as guestinfo_terms_conditions,
                guestinfo.vehicle_reg,
                guestinfo.completed as guestinfo_completed,
                guestinfo.bank_type as guestinfo_bank_type,
                guestinfo.swift_code as guestinfo_swift_code,
                guestinfo.pay_type as guestinfo_pay_type,
                guestinfo.guest_alternative_email_address,
                guestinfo.guest_id as guestinfo_guest_id,
                guestinfo.vehicle_image_path,
                guestinfo.selfie_image_path,
                guestinfo.other_guests_data,
                guestinfo.mail_sent_to_body_corp,
                guestinfo.available_vehicle_status,
                JSON_ARRAYAGG(
                            JSON_OBJECT(
                                'id', changes.id,
                                'user_id', changes.user_id,
                                'user_name', changes.user_name,
                                'record_id', changes.record_id,
                                'field', changes.field
                            )
                        ) AS changes
                "
            )
                ->groupBy(
                    'booking.id',
                    'prop.id',
                    'body_corp.id',
                    'guestinfo.id'
                )
                ->get();

            $prop_recs = DB::connection('remote_test')
                ->table('virtualdesigns_properties_properties')
                ->where('deleted_at', '=', null)
                ->orderBy('name')
                ->get();

            $selected_date_properties = $bookings->pluck('prop_name as name')->unique()->sort()->values()->toArray();
            $uniqueBookingRefs = $bookings->pluck('booking_ref')->unique()->sort()->values()->toArray();
            $uniqueGuestNames = $bookings->pluck('client_name')->unique()->sort()->values()->toArray();

            $uniqueBodyCorps = $bookings->map(function ($item) {
                if (!is_null($item->body_corp_name) && !is_null($item->id)) {
                    return [
                        'name' => $item->body_corp_name,
                        'id' => $item->id,
                    ];
                }
            })->filter()->unique()->values();

            $bodyCorpDefault = DB::connection('remote_test')
                ->table('virtualdesigns_bodycorp_bodycorp')
                ->whereNull('deleted_at')
                ->where('id', '=', 1)->first();
            $defaultCorp = $bodyCorpDefault;

            Log::debug($filter_values);

            if (str_contains($routeName, 'collect-guest-details')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'title' => 'Collect Guest Details',
                        'filter_values' => $filter_values,
                        'bookings' => $bookings,
                        'prop_recs' => $prop_recs,
                        'uniqueBookingRefs' => $uniqueBookingRefs,
                        'uniqueGuestNames' => $uniqueGuestNames,
                        'uniqueBodyCorps' => $uniqueBodyCorps,
                        'bodyCorpDefault' => $bodyCorpDefault,
                        'selected_date_properties' => $selected_date_properties,
                        'default_bodycorp' => $defaultCorp,
                        'allBodyCorps' => $allBodyCorps,
                    ],
                ]);
            } elseif (str_contains($routeName, 'guest-details-collected')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'title' => 'Guest Details Collected',
                        'filter_values' => $filter_values,
                        'bookings' => $bookings,
                        'prop_recs' => $prop_recs,
                        'uniqueBookingRefs' => $uniqueBookingRefs,
                        'uniqueGuestNames' => $uniqueGuestNames,
                        'uniqueBodyCorps' => $uniqueBodyCorps,
                        'bodyCorpDefault' => $bodyCorpDefault,
                        'selected_date_properties' => $selected_date_properties,
                        'default_bodycorp' => $defaultCorp,
                        'allBodyCorps' => $allBodyCorps,
                    ],
                ]);
            } elseif (str_contains($routeName, 'sent-to-body-corp')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'title' => 'Sent To Body Corporate',
                        'filter_values' => $filter_values,
                        'bookings' => $bookings,
                        'prop_recs' => $prop_recs,
                        'uniqueBookingRefs' => $uniqueBookingRefs,
                        'uniqueGuestNames' => $uniqueGuestNames,
                        'uniqueBodyCorps' => $uniqueBodyCorps,
                        'bodyCorpDefault' => $bodyCorpDefault,
                        'selected_date_properties' => $selected_date_properties,
                        'default_bodycorp' => $defaultCorp,
                        'allBodyCorps' => $allBodyCorps,
                    ],
                ]);
            } elseif (str_contains($routeName, 'past-completed')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'title' => 'Past Completed',
                        'filter_values' => $filter_values,
                        'bookings' => $bookings,
                        'prop_recs' => $prop_recs,
                        'uniqueBookingRefs' => $uniqueBookingRefs,
                        'uniqueGuestNames' => $uniqueGuestNames,
                        'uniqueBodyCorps' => $uniqueBodyCorps,
                        'bodyCorpDefault' => $bodyCorpDefault,
                        'selected_date_properties' => $selected_date_properties,
                        'default_bodycorp' => $defaultCorp,
                        'allBodyCorps' => $allBodyCorps,
                    ],
                ]);
            } elseif (str_contains($routeName, 'past-incompleted')) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'title' => 'Past Incompleted',
                        'filter_values' => $filter_values,
                        'bookings' => $bookings,
                        'prop_recs' => $prop_recs,
                        'uniqueBookingRefs' => $uniqueBookingRefs,
                        'uniqueGuestNames' => $uniqueGuestNames,
                        'uniqueBodyCorps' => $uniqueBodyCorps,
                        'bodyCorpDefault' => $bodyCorpDefault,
                        'selected_date_properties' => $selected_date_properties,
                        'default_bodycorp' => $defaultCorp,
                        'allBodyCorps' => $allBodyCorps,
                    ],
                ]);
            }
        } elseif (isset($request->collect_guest_details_completed_status)) {
            Log::DEBUG("Collect Guest Details Completed Status");

            // Check if booking exits
            $booking = DB::connection('remote_test')
                ->table('virtualdesigns_erpbookings_guestinfo as info')
                ->where('info.booking_id', '=', $guest_sent_reg['guest_booking_id'])
                ->get();

            $bodyCorpRule = DB::connection('remote_test')
                ->table('virtualdesigns_erpbookings_erpbookings as booking')
                ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                ->where('booking.id', '=', $guest_sent_reg['guest_booking_id'])
                ->select("booking.id", "prop.id as property_id", "prop.bodycorp_id")
                ->get();

            // Checking if guest rulle is null if it is null I am setting the default guest rule to the property.
            if ($bodyCorpRule[0]->bodycorp_id == null) {
                DB::connection('remote_test')->table('virtualdesigns_properties_properties')
                    ->where('id', '=', $bodyCorpRule[0]->property_id)
                    ->update(['bodycorp_id' => 1]);
            }

            // Before doing an update we want to see what do we need to update
            // Check if updateData has data to insert
            $updateData = [];
            $grouped = [];
            $groupArrayWithoutNumberedIndex = [];
            // Because we have the old values
            // because we know what the previous booking details was for client_name, client_phone, client_email, booking_ref, guest_id_no, booking_id, no_guests
            // We now want to compare these values
            // $userfiltered

            Log::debug("Update Record");
            if ($request->send_guest_registration === 'Primary Guest Details') {

                if ($request->filled('guest_booking_id')) {
                    $updateData['booking_id'] = $request->guest_booking_id;
                }
                // Here is a comparison condition
                Log::debug("Does old guestinfo booking exist-2");
                if (count($getOldGuestInfo) > 0) {
                    Log::debug("Yes, old guestinfo booking exist-2");
                    // This comparison should be based on the old get info of the guestinfo bookings table
                    if ($request->filled('guest_number_of_guest')) {
                        if (strcasecmp($getBookingForComparison[0]->no_guests, $request->guest_number_of_guest) !== 0) {
                            $updateData['guest_no'] = $request->guest_number_of_guest;
                        } elseif (strcasecmp($getBookingForComparison[0]->no_guests, $getOldGuestInfo[0]->guest_no) !== 0) {
                            $updateData['guest_no'] = $request->guest_number_of_guest;
                        } else {
                            $updateData['guest_no'] = $request->guest_number_of_guest;
                        }
                    }
                    if ($request->filled('guest_fullname')) {
                        if (strcasecmp($getBookingForComparison[0]->client_name, $request->guest_fullname) !== 0) {
                            $updateData['guest_name'] = $request->guest_fullname;
                        }
                    }
                    if ($request->filled('guest_phone_number')) {
                        if (strcasecmp($getBookingForComparison[0]->client_phone, $request->guest_phone_number) !== 0) {
                            $updateData['guest_contact'] = $request->guest_phone_number;
                        }
                    }
                    if ($request->filled('guest_email')) {
                        if (strcasecmp($getBookingForComparison[0]->client_email, $request->guest_email) !== 0) {
                            $updateData['guest_email'] = $request->guest_email;
                        }
                    }
                    if ($request->filled('guest_id_number')) {
                        if (strcasecmp($getBookingForComparison[0]->guest_id_no, $request->guest_id_number) !== 0) {
                            $updateData['guest_id_no'] = $request->guest_id_number;
                        }
                    }
                } else {
                    Log::debug("Nope, old guestinfo booking does not exist-2");
                    if ($request->filled('guest_number_of_guest')) {
                        if (strcasecmp($getBookingForComparison[0]->no_guests, $request->guest_number_of_guest) !== 0) {
                            $updateData['guest_no'] = $request->guest_number_of_guest;
                        }
                    }
                    if ($request->filled('guest_fullname')) {
                        if (strcasecmp($getBookingForComparison[0]->client_name, $request->guest_fullname) !== 0) {
                            $updateData['guest_name'] = $request->guest_fullname;
                        }
                    }
                    if ($request->filled('guest_phone_number')) {
                        if (strcasecmp($getBookingForComparison[0]->client_phone, $request->guest_phone_number) !== 0) {
                            $updateData['guest_contact'] = $request->guest_phone_number;
                        }
                    }
                    if ($request->filled('guest_email')) {
                        if (strcasecmp($getBookingForComparison[0]->client_email, $request->guest_email) !== 0) {
                            $updateData['guest_email'] = $request->guest_email;
                        }
                    }
                    if ($request->filled('guest_id_number')) {
                        if (strcasecmp($getBookingForComparison[0]->guest_id_no, $request->guest_id_number) !== 0) {
                            $updateData['guest_id_no'] = $request->guest_id_number;
                        }
                    }
                }

                if ($request->filled('guest_vehicle_registration_number')) {
                    $updateData['vehicle_reg'] = $request->guest_vehicle_registration_number;
                }

                if ($request->collect_guest_details_completed_status === 'on') {
                    $updateData['completed'] = 1;
                }

                if ($request->filled('available_vehicle_status')) {
                    if ($request->available_vehicle_status == 'on') {
                        $updateData['available_vehicle_status'] = 1;
                    } else {
                        $updateData['available_vehicle_status'] = 0;
                    }
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
                    $updateData['guest_id'] = env('FTP_ROOT_PATH') . '/' . $idImagePath;
                }

                if ($request->hasFile('guest_vehicle_registration_image_upload_form_file')) {
                    $file = $request->file('guest_vehicle_registration_image_upload_form_file');
                    $extension = $file->getClientOriginalExtension();
                    $filename = $request->guest_booking_ref . '-guest-vehicle-reg-test.' . $extension;
                    // Upload to FTP/SFTP
                    $disk = Storage::disk('ftp'); // or 'sftp'
                    $disk->putFileAs('images', $file, $filename);
                    $vehicleImagePath = 'images/' . $filename;
                    $updateData['vehicle_image_path'] = env('FTP_ROOT_PATH') . '/' . $vehicleImagePath;
                }

                if ($request->hasFile('guest_selfie_image_upload_form_file')) {
                    $file = $request->file('guest_selfie_image_upload_form_file');
                    $extension = $file->getClientOriginalExtension();
                    $filename = $request->guest_booking_ref . '-guest-selfie-image-test.' . $extension;
                    // Upload to FTP/SFTP
                    $disk = Storage::disk('ftp'); // or 'sftp'
                    $disk->putFileAs('images', $file, $filename);
                    $vehicleImagePath = 'images/' . $filename;
                    $updateData['selfie_image_path'] = env('FTP_ROOT_PATH') . '/' . $vehicleImagePath;
                }

                if (count($booking) === 0) {
                    $updateData['created_at'] = date('Y-m-d H:i:s');
                }
            }

            if ((int)$request->guest_number_of_guest > 1) {
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

            foreach ($grouped as $key => $value) {
                array_push($groupArrayWithoutNumberedIndex, $value);
            }

            if (!empty($groupArrayWithoutNumberedIndex)) {

                // In this function we want to find the key that has guest_id_image_upload_form_file_ in
                foreach ($groupArrayWithoutNumberedIndex as $key => $value) {

                    foreach ($value as $guestkey => $guestvalue) {
                        if (strpos($guestkey, 'guest_id_image_upload_form_file') === 0) {
                            if ($request->hasFile($guestkey)) {
                                $file_1 = $request->file($guestkey);
                                $extension_1 = $file_1->getClientOriginalExtension();
                                // when naming the image wecan have a count or index to add the guest 
                                $filename_1 = $request->guest_booking_ref . '-additional-guestid-test.' . $extension_1;

                                // Upload to FTP/SFTP
                                $disk_1 = Storage::disk('ftp'); // or 'sftp'
                                $disk_1->putFileAs('images', $file_1, $filename_1);

                                $otherIdImagePath_1 = 'images/' . $filename_1;
                                $groupArrayWithoutNumberedIndex[$key]['other_image_path'] = env('FTP_ROOT_PATH') . '/' . $otherIdImagePath_1;
                            }
                        }

                        if (strpos($guestkey, 'guest_vehicle_registration_image_upload_form_file') === 0) {
                            if ($request->hasFile($guestkey)) {
                                $file_2 = $request->file($guestkey);
                                $extension_2 = $file_2->getClientOriginalExtension();
                                $filename_2 = $request->guest_booking_ref . '-additional-guest-vehicle-reg-test.' . $extension_2;

                                // Upload to FTP/SFTP
                                $disk_2 = Storage::disk('ftp'); // or 'sftp'
                                $disk_2->putFileAs('images', $file_2, $filename_2);

                                $otherVehicleImagePath_2 = 'images/' . $filename_2;
                                $groupArrayWithoutNumberedIndex[$key]['other_vehicle_image_path'] = env('FTP_ROOT_PATH') . '/' . $otherVehicleImagePath_2;
                            }
                        }

                        if (strpos($guestkey, 'guest_selfie_image_upload_form_file') === 0) {
                            if ($request->hasFile($guestkey)) {
                                $file_3 = $request->file($guestkey);
                                $extension_3 = $file_3->getClientOriginalExtension();
                                $filename_3 = $request->guest_booking_ref . '-additional-guest-selfie-test.' . $extension_3;

                                // Upload to FTP/SFTP
                                $disk_3 = Storage::disk('ftp'); // or 'sftp'
                                $disk_3->putFileAs('images', $file_3, $filename_3);

                                $otherVehicleImagePath_3 = 'images/' . $filename_3;
                                $groupArrayWithoutNumberedIndex[$key]['other_selfie_image_path'] = env('FTP_ROOT_PATH') . '/' . $otherVehicleImagePath_3;
                            }
                        }
                    };
                }

                $updateData['other_guests_data'] = json_encode($groupArrayWithoutNumberedIndex);
            }

            // get all the fields that was updated

            $keys = array_keys($updateData);

            $fieldsUpdated = implode(', ', $keys);

            if ($request->collect_guest_details_completed_status == 'on' && count($booking) > 0) {
                Log::debug("Update Guest Details: ");
                // Update Guest Details

                $updateRecord = DB::connection('remote_test')->table('virtualdesigns_erpbookings_guestinfo')
                    ->where('booking_id', '=', $request->guest_booking_id)
                    ->update($updateData);

                if ($updateRecord) {
                    DB::connection('remote_test')->table('virtualdesigns_erpbookings_guestinfo')
                        ->where('booking_id', '=', $request->guest_booking_id)
                        ->update(['updated_at' => date('Y-m-d H:i:s')]);

                    DB::connection('remote_test')->table('virtualdesigns_changes_changes')
                        ->insert([
                            'user_id' => $user_id,
                            'user_name' => $user_name,
                            'db_table' => 'virtualdesigns_erpbookings_guestinfo',
                            'record_id' => $request->guest_booking_id,
                            'field' => $fieldsUpdated,
                            'change_date' => date("Y-m-d H:i:s"),
                            'old' => json_encode([
                                "guest_name" => $getOldGuestInfo[0]->guest_name,
                                "guest_id_no" => $getOldGuestInfo[0]->guest_id_no,
                                "guest_contact" => $getOldGuestInfo[0]->guest_contact,
                                "guest_id" => $getOldGuestInfo[0]->guest_id,
                                "guest_no" => $getOldGuestInfo[0]->guest_no,
                                "vehicle_reg" => $getOldGuestInfo[0]->vehicle_reg,
                                "completed" => $getOldGuestInfo[0]->completed,
                                "booking_id" => $getOldGuestInfo[0]->booking_id,
                                "guest_alternative_email_address" => $getOldGuestInfo[0]->guest_alternative_email_address,
                                "other_guests_data" => $getOldGuestInfo[0]->other_guests_data,
                                "mail_sent_to_body_corp" => $getOldGuestInfo[0]->mail_sent_to_body_corp,
                                "selfie_image_path" => $getOldGuestInfo[0]->selfie_image_path,
                                "available_vehicle_status" => $getOldGuestInfo[0]->available_vehicle_status,
                            ]),
                            'new' => json_encode([$updateData])
                        ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Guest Details Update Successfully',
                    ]);
                } else {
                    return response()->json([
                        'success' => true,
                        'message' => 'Details already filled in.',
                    ]);
                }
            } elseif ($request->collect_guest_details_completed_status == 'on' && count($booking) === 0 && count($updateData) > 0) {
                Log::debug("Insert Guest Details: ");
                // Insert Guest Details
                DB::connection('remote_test')->table('virtualdesigns_erpbookings_guestinfo')
                    ->insert($updateData);

                DB::connection('remote_test')->table('virtualdesigns_changes_changes')
                    ->insert([
                        'user_id' => $user_id,
                        'user_name' => $user_name,
                        'db_table' => 'virtualdesigns_erpbookings_guestinfo',
                        'record_id' => $request->guest_booking_id,
                        'field' => $fieldsUpdated,
                        'change_date' => date("Y-m-d H:i:s"),
                        'old' => null,
                        'new' => json_encode([$updateData])
                    ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Guest Details Successfully Completed',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to Complete Guest Details',
                ], 500);
            }
        } elseif (count($getOldGuestInfo) != 0 && $request['guest_number_of_guest'] < 2 && !isset($request->guest_send_email_response)) {
            Log::debug("Finds all the details");
            $updateData = [];
            $idImagePath = null;
            $vehicleImagePath = null;
            $selfieImagePath = null;

            if ($request->send_guest_registration === 'Primary Guest Details') {
                if ($request->filled('guest_number_of_guest')) {
                    $updateData['guest_no'] = $request->guest_number_of_guest;
                }
                if ($request->filled('guest_booking_id')) {
                    $updateData['booking_id'] = $request->guest_booking_id;
                }
                if ($request->filled('guest_fullname')) {
                    $updateData['guest_name'] = $request->guest_fullname;
                }
                if ($request->filled('guest_phone_number')) {
                    $updateData['guest_contact'] = $request->guest_phone_number;
                }
                if ($request->filled('guest_email')) {
                    $updateData['guest_alternative_email_address'] = $request->guest_email;
                }
                if ($request->filled('guest_id_number')) {
                    $updateData['guest_id_no'] = $request->guest_id_number;
                }
                if ($request->filled('guest_vehicle_registration_number')) {
                    $updateData['vehicle_reg'] = $request->guest_vehicle_registration_number;
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
                    $updateData['guest_id'] = env('FTP_ROOT_PATH') . '/' . $idImagePath;
                }

                if ($request->hasFile('guest_vehicle_registration_image_upload_form_file')) {
                    $vehicle_file = $request->file('guest_vehicle_registration_image_upload_form_file');
                    $vehicle_extension = $vehicle_file->getClientOriginalExtension();
                    $vehicle_filename = $request->guest_booking_ref . '-guest-vehicle-reg-test.' . $vehicle_extension;
                    // Upload to FTP/SFTP
                    $disk = Storage::disk('ftp'); // or 'sftp'
                    $disk->putFileAs('images', $vehicle_file, $vehicle_filename);
                    $vehicleImagePath = 'images/' . $vehicle_filename;
                    $updateData['vehicle_image_path'] = env('FTP_ROOT_PATH') . '/' . $vehicleImagePath;
                }

                if ($request->hasFile('guest_selfie_image_upload_form_file')) {
                    $selfie_file = $request->file('guest_selfie_image_upload_form_file');
                    $selfie_extension = $selfie_file->getClientOriginalExtension();
                    $selfie_filename = $request->guest_booking_ref . '-guest-selfie-image-test.' . $selfie_extension;
                    // Upload to FTP/SFTP
                    $disk = Storage::disk('ftp'); // or 'sftp'
                    $disk->putFileAs('images', $selfie_file, $selfie_filename);
                    $selfieImagePath = 'images/' . $selfie_filename;
                    $updateData['selfie_image_path'] = env('FTP_ROOT_PATH') . '/' . $selfieImagePath;
                }

                if ($request->filled('guest_booking_id')) {
                    $updateData['completed'] = 1;
                } else {
                    $updateData['completed'] = 0;
                }
            }

            if (count($getOldGuestInfo) > 0 && isset($updateData) && count($updateData) > 0) {
                $updatedGuestInfo = DB::connection('remote_test')->table('virtualdesigns_erpbookings_guestinfo')
                    ->where('booking_id', '=', $request->guest_booking_id)
                    ->update($updateData);

                if ($updatedGuestInfo && isset($request->guest_booking)) {
                    DB::connection('remote_test')->table('virtualdesigns_changes_changes')
                        ->insert([
                            'user_id' => $user_id,
                            'user_name' => $user_name,
                            'db_table' => 'virtualdesigns_erpbookings_guestinfo',
                            'record_id' => $request->guest_booking_id,
                            'change_date' => date("Y-m-d H:i:s"),
                            'old' => json_encode([
                                "guest_name" => $getOldGuestInfo[0]->guest_name,
                                "guest_id_no" => $getOldGuestInfo[0]->guest_id_no,
                                "guest_contact" => $getOldGuestInfo[0]->guest_contact,
                                "guest_id" => $getOldGuestInfo[0]->guest_id,
                                "guest_no" => $getOldGuestInfo[0]->guest_no,
                                "vehicle_reg" => $getOldGuestInfo[0]->vehicle_reg,
                                "completed" => $getOldGuestInfo[0]->completed,
                                "booking_id" => $getOldGuestInfo[0]->booking_id,
                                "guest_alternative_email_address" => $getOldGuestInfo[0]->guest_alternative_email_address,
                                "other_guests_data" => $getOldGuestInfo[0]->other_guests_data,
                                "mail_sent_to_body_corp" => $getOldGuestInfo[0]->mail_sent_to_body_corp,
                                "selfie_image_path" => $getOldGuestInfo[0]->selfie_image_path,
                                "available_vehicle_status" => $getOldGuestInfo[0]->available_vehicle_status,
                            ]),
                            'new' => json_encode([
                                "guest_name" => $request->guest_fullname,
                                "guest_contact" => $request->guest_phone_number,
                                "guest_alternative_email_address" => $request->guest_email,
                                "guest_id_no" => $request->guest_id_number,
                                "vehicle_reg" => $request->guest_vehicle_registration_number,
                                "guest_id" => env('FTP_ROOT_PATH') . '/' . $idImagePath,
                                "other_guests_data" => null,
                            ])
                        ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Successfully Updated Guest Details',
                    ]);
                } else {
                    return response()->json([
                        'success' => true,
                        'message' => 'Details already filled in',
                    ]);
                }
            } else {

                $insertGuestInfo = DB::connection('remote_test')->table('virtualdesigns_erpbookings_guestinfo')
                    // Where clause is not nessessary when inserting data into a row
                    // ->where('booking_id', '=', $request->guest_booking_id)
                    ->insert($updateData);

                if ($insertGuestInfo) {
                    DB::connection('remote_test')->table('virtualdesigns_changes_changes')
                        ->insert([
                            'user_id' => $user_id,
                            'user_name' => $user_name,
                            'db_table' => 'virtualdesigns_erpbookings_guestinfo',
                            'record_id' => $request->guest_booking_id,
                            'change_date' => date("Y-m-d H:i:s"),
                            'old' => json_encode([
                                "guest_name" => $getOldGuestInfo[0]->guest_name,
                                "guest_id_no" => $getOldGuestInfo[0]->guest_id_no,
                                "guest_contact" => $getOldGuestInfo[0]->guest_contact,
                                "guest_id" => $getOldGuestInfo[0]->guest_id,
                                "guest_no" => $getOldGuestInfo[0]->guest_no,
                                "vehicle_reg" => $getOldGuestInfo[0]->vehicle_reg,
                                "completed" => $getOldGuestInfo[0]->completed,
                                "booking_id" => $getOldGuestInfo[0]->booking_id,
                                "guest_alternative_email_address" => $getOldGuestInfo[0]->guest_alternative_email_address,
                                "other_guests_data" => $getOldGuestInfo[0]->other_guests_data,
                                "mail_sent_to_body_corp" => $getOldGuestInfo[0]->mail_sent_to_body_corp,
                                "selfie_image_path" => $getOldGuestInfo[0]->selfie_image_path,
                                "available_vehicle_status" => $getOldGuestInfo[0]->available_vehicle_status,
                            ]),
                            'new' => json_encode([
                                "guest_name" => $request->guest_fullname,
                                "guest_contact" => $request->guest_phone_number,
                                "guest_alternative_email_address" => $request->guest_email,
                                "guest_id_no" => $request->guest_id_number,
                                "vehicle_reg" => $request->guest_vehicle_registration_number,
                                "guest_id" => env('FTP_ROOT_PATH') . '/' . $idImagePath,
                                "other_guests_data" => null,
                            ])
                        ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Successfully Inserted Guest Details',
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed To Insert Guest Details',
                    ], 500);
                }
            }
        } elseif (isset($request->guest_heading_completed)) {
            Log::debug("Guest Heading");
            return response()->json([
                'success' => true,
                'message' => 'OK',
            ]);
        } elseif (isset($request->guest_booking_id) && !isset($request->guest_send_email_response)) {
            Log::debug("Booking id is set");
            $updateData = [];

            // Check guest rule
            // Get guest information
            $guestRules = DB::connection('remote_test')
                ->table('virtualdesigns_erpbookings_erpbookings as booking')
                ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                ->where('booking.id', '=', $guest_sent_reg['guest_booking_id'])
                ->select("booking.id", "prop.id as property_id", "prop.bodycorp_id")
                ->get();

            // Checking if guest rulle is null if it is null I am setting the default guest rule.
            if ($guestRules[0]->bodycorp_id == null) {
                DB::connection('remote_test')->table('virtualdesigns_properties_properties')
                    ->where('id', '=', $guestRules[0]->property_id)
                    ->update(['bodycorp_id' => 1]);
            }

            $idImagePath = null;
            $vehicleImagePath = null;
            $selfieImagePath = null;
            $groupArrayWithoutNumberedIndex = [];

            if ($request->send_guest_registration === 'Primary Guest Details') {

                if ($request->filled('guest_booking_id')) {
                    $updateData['booking_id'] = $request->guest_booking_id;
                }
                // Here is a comparison condition
                Log::debug("Does old guestinfo booking exist-1");
                if (count($getOldGuestInfo) > 0) {
                    Log::debug("Yes, old guestinfo booking exist-1");
                    // This comparison should be based on the old get info of the guestinfo bookings table
                    if ($request->filled('guest_number_of_guest')) {
                        if (strcasecmp($getBookingForComparison[0]->no_guests, $request->guest_number_of_guest) !== 0) {
                            $updateData['guest_no'] = $request->guest_number_of_guest;
                        } elseif (strcasecmp($getBookingForComparison[0]->no_guests, $getOldGuestInfo[0]->guest_no) !== 0) {
                            $updateData['guest_no'] = $request->guest_number_of_guest;
                        } else {
                            $updateData['guest_no'] = $request->guest_number_of_guest;
                        }
                    }
                    if ($request->filled('guest_fullname')) {
                        if (strcasecmp($getBookingForComparison[0]->client_name, $request->guest_fullname) !== 0) {
                            $updateData['guest_name'] = $request->guest_fullname;
                        }
                    }
                    if ($request->filled('guest_phone_number')) {
                        if (strcasecmp($getBookingForComparison[0]->client_phone, $request->guest_phone_number) !== 0) {
                            $updateData['guest_contact'] = $request->guest_phone_number;
                        }
                    }
                    if ($request->filled('guest_email')) {
                        if (strcasecmp($getBookingForComparison[0]->client_email, $request->guest_email) !== 0) {
                            $updateData['guest_email'] = $request->guest_email;
                        }
                    }
                    if ($request->filled('guest_id_number')) {
                        if (strcasecmp($getBookingForComparison[0]->guest_id_no, $request->guest_id_number) !== 0) {
                            $updateData['guest_id_no'] = $request->guest_id_number;
                        }
                    }
                } else {
                    if ($request->filled('guest_number_of_guest')) {
                        $updateData['guest_no'] = $request->guest_number_of_guest;
                    }
                    if ($request->filled('guest_fullname')) {
                        if (strcasecmp($getBookingForComparison[0]->client_name, $request->guest_fullname) !== 0) {
                            $updateData['guest_name'] = $request->guest_fullname;
                        }
                    }
                    if ($request->filled('guest_phone_number')) {
                        if (strcasecmp($getBookingForComparison[0]->client_phone, $request->guest_phone_number) !== 0) {
                            $updateData['guest_contact'] = $request->guest_phone_number;
                        }
                    }
                    if ($request->filled('guest_email')) {
                        if (strcasecmp($getBookingForComparison[0]->client_email, $request->guest_email) !== 0) {
                            $updateData['guest_email'] = $request->guest_email;
                        }
                    }
                    if ($request->filled('guest_id_number')) {
                        if (strcasecmp($getBookingForComparison[0]->guest_id_no, $request->guest_id_number) !== 0) {
                            $updateData['guest_id_no'] = $request->guest_id_number;
                        }
                    }
                }

                if ($request->filled('guest_vehicle_registration_number')) {
                    $updateData['vehicle_reg'] = $request->guest_vehicle_registration_number;
                }

                if ($request->collect_guest_details_completed_status === 'on') {
                    $updateData['completed'] = 1;
                }

                if ($request->filled('available_vehicle_status')) {
                    if ($request->available_vehicle_status == 'on') {
                        $updateData['available_vehicle_status'] = 1;
                    } else {
                        $updateData['available_vehicle_status'] = 0;
                    }
                }

                // On the following condition we are saving the image to the server. Then we will be using the image path and saving it on the database
                if ($request->hasFile('guest_id_image_upload_form_file')) {
                    Log::debug("Has Image");
                    $file = $request->file('guest_id_image_upload_form_file');
                    $extension = $file->getClientOriginalExtension();
                    $filename = $request->guest_booking_ref . '-guestid-test.' . $extension;
                    // Upload to FTP/SFTP
                    $disk = Storage::disk('ftp'); // or 'sftp'
                    $disk->putFileAs('images', $file, $filename);

                    $idImagePath = 'images/' . $filename;
                    $updateData['guest_id'] = env('FTP_ROOT_PATH') . '/' . $idImagePath;
                }

                if ($request->hasFile('guest_vehicle_registration_image_upload_form_file')) {
                    Log::debug("Has Vehicle Image");
                    $file = $request->file('guest_vehicle_registration_image_upload_form_file');
                    $extension = $file->getClientOriginalExtension();
                    $filename = $request->guest_booking_ref . '-guest-vehicle-reg-test.' . $extension;
                    // Upload to FTP/SFTP
                    $disk = Storage::disk('ftp'); // or 'sftp'
                    $disk->putFileAs('images', $file, $filename);
                    $vehicleImagePath = 'images/' . $filename;
                    $updateData['vehicle_image_path'] = env('FTP_ROOT_PATH') . '/' . $vehicleImagePath;
                }

                if ($request->hasFile('guest_selfie_image_upload_form_file')) {
                    Log::debug("Has Selfie Image");
                    $file = $request->file('guest_selfie_image_upload_form_file');
                    $extension = $file->getClientOriginalExtension();
                    $filename = $request->guest_booking_ref . '-guest-selfie-image-test.' . $extension;
                    // Upload to FTP/SFTP
                    $disk = Storage::disk('ftp'); // or 'sftp'
                    $disk->putFileAs('images', $file, $filename);
                    $vehicleImagePath = 'images/' . $filename;
                    $updateData['selfie_image_path'] = env('FTP_ROOT_PATH') . '/' . $vehicleImagePath;
                }

                if (count($getOldGuestInfo) === 0) {
                    $updateData['created_at'] = date('Y-m-d H:i:s');
                }
            }

            $grouped = [];
            if ((int)$request->guest_number_of_guest > 1) {
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

            foreach ($grouped as $key => $value) {
                array_push($groupArrayWithoutNumberedIndex, $value);
            }

            if (!empty($groupArrayWithoutNumberedIndex)) {

                // In this function we want to find the key that has guest_id_image_upload_form_file_ in
                foreach ($groupArrayWithoutNumberedIndex as $key => $value) {

                    foreach ($value as $guestkey => $guestvalue) {
                        if (strpos($guestkey, 'guest_id_image_upload_form_file') === 0) {

                            if ($request->hasFile($guestkey)) {
                                $file_1 = $request->file($guestkey);
                                $extension_1 = $file_1->getClientOriginalExtension();
                                // when naming the image wecan have a count or index to add the guest 
                                $filename_1 = $request->guest_booking_ref . '-additional-guestid-test.' . $extension_1;

                                // Upload to FTP/SFTP
                                $disk_1 = Storage::disk('ftp'); // or 'sftp'
                                $disk_1->putFileAs('images', $file_1, $filename_1);

                                $otherIdImagePath_1 = 'images/' . $filename_1;
                                $groupArrayWithoutNumberedIndex[$key]['other_image_path'] = env('FTP_ROOT_PATH') . '/' . $otherIdImagePath_1;
                            }
                        }

                        if (strpos($guestkey, 'guest_vehicle_registration_image_upload_form_file') === 0) {
                            if ($request->hasFile($guestkey)) {
                                $file_2 = $request->file($guestkey);
                                $extension_2 = $file_2->getClientOriginalExtension();
                                $filename_2 = $request->guest_booking_ref . '-additional-guest-vehicle-reg-test.' . $extension_2;

                                // Upload to FTP/SFTP
                                $disk_2 = Storage::disk('ftp'); // or 'sftp'
                                $disk_2->putFileAs('images', $file_2, $filename_2);

                                $otherVehicleImagePath_2 = 'images/' . $filename_2;
                                $groupArrayWithoutNumberedIndex[$key]['other_vehicle_image_path'] = env('FTP_ROOT_PATH') . '/' . $otherVehicleImagePath_2;
                            }
                        }

                        if (strpos($guestkey, 'guest_selfie_image_upload_form_file') === 0) {
                            if ($request->hasFile($guestkey)) {
                                $file_3 = $request->file($guestkey);
                                $extension_3 = $file_3->getClientOriginalExtension();
                                $filename_3 = $request->guest_booking_ref . '-additional-guest-selfie-test.' . $extension_3;

                                // Upload to FTP/SFTP
                                $disk_3 = Storage::disk('ftp'); // or 'sftp'
                                $disk_3->putFileAs('images', $file_3, $filename_3);

                                $otherVehicleImagePath_3 = 'images/' . $filename_3;
                                $groupArrayWithoutNumberedIndex[$key]['other_selfie_image_path'] = env('FTP_ROOT_PATH') . '/' . $otherVehicleImagePath_3;
                            }
                        }
                    };
                }

                $updateData['other_guests_data'] = json_encode($groupArrayWithoutNumberedIndex);
            }

            // Pleas do checks to see everything is completed
            // $updateData['completed']
            if ($updateData['booking_id'] !== null && $updateData['booking_id'] > 0 && $updateData['guest_no'] > 0) {
                $updateData['completed'] = 1;
            } else {
                $updateData['completed'] = 0;
            }
            if ($request->filled('available_vehicle_status')) {
                if ($request->available_vehicle_status == 'on') {
                    $updateData['available_vehicle_status'] = 1;
                } else {
                    $updateData['available_vehicle_status'] = 0;
                }
            }

            // Set the default Property
            if (!isset($guestRules->bodycorp_id)) {
                $guestRules = DB::connection('remote_test')
                    ->table('virtualdesigns_erpbookings_erpbookings as booking')
                    ->join('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
                    ->where('booking.id', '=', $request->guest_booking_id)
                    ->update(['prop.bodycorp_id' => 1]);
            }

            $checkIfGuestBookingIdIsPresent = DB::connection('remote_test')->table('virtualdesigns_erpbookings_guestinfo')
                ->where('booking_id', '=', $request->guest_booking_id)
                ->get();

            $keys = array_keys($updateData);

            $fieldsUpdated = implode(', ', $keys);

            if (!empty($updateData) && count($checkIfGuestBookingIdIsPresent) > 0) {
                Log::debug('updateData is not empty');

                $updatedGuestInfo = DB::connection('remote_test')->table('virtualdesigns_erpbookings_guestinfo')
                    ->where('booking_id', '=', $request->guest_booking_id)
                    ->update($updateData);

                if ($updatedGuestInfo) {
                    DB::connection('remote_test')->table('virtualdesigns_erpbookings_guestinfo')
                        ->where('booking_id', '=', $request->guest_booking_id)
                        ->update(['updated_at' => date('Y-m-d H:i:s')]);

                    DB::connection('remote_test')->table('virtualdesigns_changes_changes')
                        ->insert([
                            'user_id' => $user_id,
                            'user_name' => $user_name,
                            'db_table' => 'virtualdesigns_erpbookings_guestinfo',
                            'record_id' => $request->guest_booking_id,
                            'field' => $fieldsUpdated,
                            'change_date' => date("Y-m-d H:i:s"),
                            'old' => json_encode([
                                "guest_name" => $getOldGuestInfo[0]->guest_name,
                                "guest_id_no" => $getOldGuestInfo[0]->guest_id_no,
                                "guest_contact" => $getOldGuestInfo[0]->guest_contact,
                                "guest_id" => $getOldGuestInfo[0]->guest_id,
                                "guest_no" => $getOldGuestInfo[0]->guest_no,
                                "vehicle_reg" => $getOldGuestInfo[0]->vehicle_reg,
                                "completed" => $getOldGuestInfo[0]->completed,
                                "booking_id" => $getOldGuestInfo[0]->booking_id,
                                "guest_alternative_email_address" => $getOldGuestInfo[0]->guest_alternative_email_address,
                                "other_guests_data" => $getOldGuestInfo[0]->other_guests_data,
                                "mail_sent_to_body_corp" => $getOldGuestInfo[0]->mail_sent_to_body_corp,
                                "selfie_image_path" => $getOldGuestInfo[0]->selfie_image_path,
                                "available_vehicle_status" => $getOldGuestInfo[0]->available_vehicle_status,
                            ]),
                            'new' => json_encode([
                                "guest_name" => $request->guest_fullname,
                                "guest_contact" => $request->guest_phone_number,
                                "guest_alternative_email_address" => $request->guest_email,
                                "guest_id_no" => $request->guest_id_number,
                                "vehicle_reg" => $request->guest_vehicle_registration_number,
                                "guest_id" => env('FTP_ROOT_PATH') . '/' . $idImagePath,
                                "other_guests_data" => null,
                            ])
                        ]);

                    return response()->json([
                        'success' => true,
                        'message' => 'Successfully Updated Guest Details',
                    ]);
                } else {
                    return response()->json([
                        'success' => true,
                        'message' => 'Details already filled in',
                    ]);
                }
            } elseif (count($checkIfGuestBookingIdIsPresent) == 0) {
                // Still need to do the insert of guest details to see who made the changes and what changes was made
                $insertGuestInfo = DB::connection('remote_test')->table('virtualdesigns_erpbookings_guestinfo')
                    // Where clause is not nessessary when inserting data into a row
                    // ->where('booking_id', '=', $request->guest_booking_id)
                    ->insert($updateData);

                if ($insertGuestInfo && count($getOldGuestInfo) < 1) {
                    if (count($getOldGuestInfo) < 1) {

                        DB::connection('remote_test')->table('virtualdesigns_changes_changes')
                            ->insert([
                                'user_id' => $user_id,
                                'user_name' => $user_name,
                                'db_table' => 'virtualdesigns_erpbookings_guestinfo',
                                'record_id' => $request->guest_booking_id,
                                'field' => $fieldsUpdated,
                                'change_date' => date("Y-m-d H:i:s"),
                                'old' => null,
                                'new' => json_encode([
                                    "guest_name" => $request->guest_fullname,
                                    "guest_contact" => $request->guest_phone_number,
                                    "guest_alternative_email_address" => $request->guest_email,
                                    "guest_id_no" => $request->guest_id_number,
                                    "vehicle_reg" => $request->guest_vehicle_registration_number,
                                    "guest_id" => env('FTP_ROOT_PATH') . '/' . $idImagePath,
                                    "other_guests_data" => null,
                                ])
                            ]);

                        return response()->json([
                            'success' => true,
                            'message' => 'Successfully Inserted Guest Details',
                        ]);
                    } else {
                        DB::connection('remote_test')->table('virtualdesigns_changes_changes')
                            ->insert([
                                'user_id' => $user_id,
                                'user_name' => $user_name,
                                'db_table' => 'virtualdesigns_erpbookings_guestinfo',
                                'record_id' => $request->guest_booking_id,
                                'change_date' => date("Y-m-d H:i:s"),
                                'old' => json_encode([
                                    "guest_name" => $getOldGuestInfo[0]->guest_name,
                                    "guest_id_no" => $getOldGuestInfo[0]->guest_id_no,
                                    "guest_contact" => $getOldGuestInfo[0]->guest_contact,
                                    "guest_id" => $getOldGuestInfo[0]->guest_id,
                                    "guest_no" => $getOldGuestInfo[0]->guest_no,
                                    "vehicle_reg" => $getOldGuestInfo[0]->vehicle_reg,
                                    "completed" => $getOldGuestInfo[0]->completed,
                                    "booking_id" => $getOldGuestInfo[0]->booking_id,
                                    "guest_alternative_email_address" => $getOldGuestInfo[0]->guest_alternative_email_address,
                                    "other_guests_data" => $getOldGuestInfo[0]->other_guests_data,
                                    "mail_sent_to_body_corp" => $getOldGuestInfo[0]->mail_sent_to_body_corp,
                                    "selfie_image_path" => $getOldGuestInfo[0]->selfie_image_path,
                                    "available_vehicle_status" => $getOldGuestInfo[0]->available_vehicle_status,
                                ]),
                                'new' => json_encode([
                                    "guest_name" => $request->guest_fullname,
                                    "guest_contact" => $request->guest_phone_number,
                                    "guest_alternative_email_address" => $request->guest_email,
                                    "guest_id_no" => $request->guest_id_number,
                                    "vehicle_reg" => $request->guest_vehicle_registration_number,
                                    "guest_id" => env('FTP_ROOT_PATH') . '/' . $idImagePath,
                                    "other_guests_data" => null,
                                ])
                            ]);
                        return response()->json([
                            'success' => true,
                            'message' => 'Successfully Inserted Guest Details',
                        ]);
                    }
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed To Insert Guest Details',
                    ], 500);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'OK',
            ]);
        } elseif (isset($request->guest_send_email_response) && isset($request->guest_booking_id)) {
            Log::debug('Guest Send Email Response');
            // Update the email response only when the Guest Details Collected Page
            if (isset($getOldGuestInfo[0]->booking_id)) {
                if ($guest_sent_reg['guest_send_email_response'] == "on" && $getOldGuestInfo[0]->completed == 1) {
                    Log::debug('Yes, guest email response was sent');
                    DB::connection('remote_test')->table('virtualdesigns_erpbookings_guestinfo')
                        ->where('booking_id', '=', $guest_sent_reg['guest_booking_id'])
                        ->update(['mail_sent_to_body_corp' => 1, 'updated_at' => date("Y-m-d H:i:s")]);

                    DB::connection('remote_test')->table('virtualdesigns_changes_changes')
                        ->insert([
                            'user_id' => $user_id,
                            'user_name' => $user_name,
                            'db_table' => 'virtualdesigns_erpbookings_guestinfo',
                            'record_id' => $request->guest_booking_id,
                            'field' => 'mail_sent_to_body_corp, updated_at',
                            'change_date' => date("Y-m-d H:i:s"),
                            'old' => null,
                            'new' => json_encode([
                                "mail_sent_to_body_corp" => 1,
                                'updated_at' => date("Y-m-d H:i:s")
                            ])
                        ]);
                    return response()->json([
                        'success' => true,
                        'message' => "Success - Moved to 'Sent To Body Corp'",
                    ]);
                }
            }
            return response()->json([
                'success' => false,
                'message' => "Failed Moved to 'Sent To Body Corp'",
            ], 409);
        } else {
            return response()->json([
                'success' => true,
                'message' => 'OK',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'OK',
        ]);
    }

    public function fetchBookings(Request $request): JsonResponse
    {
        $perPage = 10;   // number of rows you want to load at a time

        $bookings = DB::connection('remote_test')
            ->table('virtualdesigns_erpbookings_erpbookings as booking')
            ->where('booking.status', '!=', 1)
            ->where('booking.deleted_at', '=', null)
            ->where('booking.quote_confirmed', '=', 1)
            ->where('booking.arrival_date', '=', date('Y-m-d'))
            ->Leftjoin('virtualdesigns_properties_properties as prop', 'booking.property_id', '=', 'prop.id')
            ->join('virtualdesigns_erpbookings_guestinfo as guest_info', 'guest_info.booking_id', '=', 'booking.id')
            ->where('guest_info.completed', '=', 1)
            ->where('guest_info.mail_sent_to_body_corp', '=', 1)
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
                'booking.client_email',
                'booking.booking_ref',
                'booking.id as booking_id',
                'booking.no_guests',
                'guest_info.id as guest_info_id',
                'guest_info.guest_name',
                'guest_info.guest_id_no',
                'guest_info.guest_contact',
                'guest_info.guest_email',
                'guest_info.guest_no',
                'guest_info.eta',
                'guest_info.etd',
                'guest_info.flight_number',
                'guest_info.bank_ac_name',
                'guest_info.bank_ac_no',
                'guest_info.bank_name',
                'guest_info.bank_code',
                'guest_info.no_smoking',
                'guest_info.noise_policy',
                'guest_info.fair_usage_policy',
                'guest_info.breakage_policy',
                'guest_info.terms_conditions',
                'guest_info.booking_id as guest_info_booking_id',
                'guest_info.bank_type',
                'guest_info.swift_code',
                'guest_info.pay_type',
                'guest_info.guest_alternative_email_address',
                'guest_info.guest_id',
                'guest_info.completed',
                'guest_info.vehicle_image_path',
                'guest_info.other_guests_data',
                'guest_info.selfie_image_path',
                'guest_info.mail_sent_to_body_corp',
                'guest_info.available_vehicle_status',
            )
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $bookings,
        ]);
    }
}
