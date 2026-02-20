<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class ReviewsController extends Controller
{
    public function index(Request $request)
    {
        $propId = $request->header('propid');

        $reviews = DB::table('virtualdesigns_reviews_reviews as reviews')
            ->leftJoin('virtualdesigns_properties_properties as property', 'reviews.property_id', '=', 'property.id')
            ->select('reviews.*', 'property.name as property_name');

        if ($propId) {
            $reviews->where('reviews.property_id', '=', $propId);
        }

        return $this->corsJson([
            'reviews' => $reviews->get(),
        ], 200);
    }

    public function updateReview(Request $request, int $id)
    {
        DB::table('virtualdesigns_reviews_reviews')
            ->where('id', '=', $id)
            ->update($request->all());

        return $this->corsJson(['success' => true], 200);
    }

    public function sendReview(Request $request)
    {
        try {
            $propertyId = (int) $request->input('property_id');
            $property = DB::table('virtualdesigns_properties_properties')->where('id', '=', $propertyId)->first();
            $clientEmail = $request->input('client_email');

            if (!$property || !$clientEmail) {
                return $this->corsJson(['error' => 'Property and client email are required'], 400);
            }

            $url = 'https://hostagents.co.za/review-form?prop_id=' . $propertyId;
            $url .= '&client_name=' . urlencode((string) $request->input('client_name'));
            $url .= '&client_email=' . urlencode((string) $clientEmail);
            $url .= '&arrival_date=' . urlencode((string) $request->input('arrival_date'));
            $url .= '&departure_date=' . urlencode((string) $request->input('departure_date'));

            $vars = [
                'prop_name' => $property->name,
                'client_name' => $request->input('client_name'),
                'arrival_date' => $request->input('arrival_date'),
                'departure_date' => $request->input('departure_date'),
                'url' => $url,
            ];

            $altEmail = null;
            $booking = DB::table('virtualdesigns_erpbookings_erpbookings')
                ->where('property_id', '=', $propertyId)
                ->where('arrival_date', '=', $request->input('arrival_date'))
                ->where('departure_date', '=', $request->input('departure_date'))
                ->where('status', '!=', 1)
                ->whereNull('deleted_at')
                ->first();

            if ($booking) {
                $guestInfo = DB::table('virtualdesigns_erpbookings_guestinfo')
                    ->where('booking_id', '=', $booking->id)
                    ->first();
                if ($guestInfo && !empty($guestInfo->guest_alternative_email_address) && str_contains($guestInfo->guest_alternative_email_address, '@')) {
                    $altEmail = $guestInfo->guest_alternative_email_address;
                }
            }

            Mail::send('mail.review_request', $vars, function ($message) use ($clientEmail, $property, $altEmail) {
                $message->to($clientEmail, 'Property Review');
                if ($altEmail) {
                    $message->cc($altEmail);
                }
                $message->subject('Review your stay at ' . $property->name);
            });

            return $this->corsJson(['success' => true], 200);
        } catch (\Throwable $e) {
            if ($e instanceof HttpResponseException) {
                return $e->getResponse();
            }
            return $this->corsJson(['error' => $e->getMessage()], 500);
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
