<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListingTypesController extends Controller
{
    public function index(Request $request)
    {
        $this->assertApiKey($request);

        $listingTypes = DB::table('virtualdesigns_properties_listing_types')->get();

        return $this->corsJson($listingTypes, 200);
    }

    public function show(Request $request, $typeid)
    {
        $this->assertApiKey($request);

        $listingType = DB::table('virtualdesigns_properties_listing_types')->where('id', '=', $typeid)->first();

        return $this->corsJson($listingType, 200);
    }

    public function store(Request $request)
    {
        $this->assertApiKey($request);

        $id = DB::table('virtualdesigns_properties_listing_types')->insertGetId([
            'name' => $request->input('name'),
            'slug' => Str::slug((string) $request->input('name')),
            'is_room_type' => $request->input('is_room_type'),
        ]);

        $listingType = DB::table('virtualdesigns_properties_listing_types')->where('id', '=', $id)->first();

        return $this->corsJson($listingType, 200);
    }

    public function update(Request $request, $id)
    {
        $this->assertApiKey($request);

        DB::table('virtualdesigns_properties_listing_types')->where('id', '=', $id)->update([
            'name' => $request->input('name'),
            'slug' => Str::slug((string) $request->input('name')),
            'is_room_type' => $request->input('is_room_type'),
        ]);

        $listingType = DB::table('virtualdesigns_properties_listing_types')->where('id', '=', $id)->first();

        return $this->corsJson($listingType, 200);
    }

    public function destroy(Request $request, $id)
    {
        $this->assertApiKey($request);

        DB::table('virtualdesigns_properties_listing_types')->where('id', '=', $id)->delete();

        return $this->corsJson('Listing Type Deleted', 200);
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
