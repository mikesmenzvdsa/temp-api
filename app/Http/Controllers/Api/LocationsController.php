<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LocationsController extends Controller
{
    public function index(Request $request)
    {
        $this->assertApiKey($request);

        if ($request->header('grouplocations') !== null) {
            $locations = DB::table('virtualdesigns_locations_locations')
                ->orderBy('location_type')
                ->orderBy('name')
                ->get()
                ->groupBy('location_type');

            return $this->corsJson($locations, 200);
        }

        $locations = DB::table('virtualdesigns_locations_locations')->get();

        return $this->corsJson($locations, 200);
    }

    public function show(Request $request, $locid)
    {
        $this->assertApiKey($request);

        $location = DB::table('virtualdesigns_locations_locations')->where('id', '=', $locid)->first();

        return $this->corsJson($location, 200);
    }

    public function store(Request $request)
    {
        $this->assertApiKey($request);

        $id = DB::table('virtualdesigns_locations_locations')->insertGetId([
            'name' => $request->input('name'),
            'slug' => Str::slug((string) $request->input('name')),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'description' => $request->input('description'),
            'meta_keywords' => $request->input('meta_keywords'),
            'meta_title' => $request->input('meta_title'),
            'parent_id' => $request->input('parent_id'),
            'location_type' => $request->input('location_type'),
            'show_bhr' => $request->input('show_bhr'),
            'show_hhr' => $request->input('show_hhr'),
            'show_nhr' => $request->input('show_nhr'),
            'show_yzf' => $request->input('show_yzf'),
            'show_ghr' => $request->input('show_ghr'),
        ]);

        $location = DB::table('virtualdesigns_locations_locations')->where('id', '=', $id)->first();

        return $this->corsJson($location, 200);
    }

    public function update(Request $request, $id)
    {
        $this->assertApiKey($request);

        DB::table('virtualdesigns_locations_locations')->where('id', '=', $id)->update([
            'name' => $request->input('name'),
            'slug' => Str::slug((string) $request->input('name')),
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'description' => $request->input('description'),
            'meta_keywords' => $request->input('meta_keywords'),
            'meta_title' => $request->input('meta_title'),
            'parent_id' => $request->input('parent_id'),
            'location_type' => $request->input('location_type'),
            'show_bhr' => $request->input('show_bhr'),
            'show_hhr' => $request->input('show_hhr'),
            'show_nhr' => $request->input('show_nhr'),
            'show_yzf' => $request->input('show_yzf'),
            'show_ghr' => $request->input('show_ghr'),
        ]);

        $location = DB::table('virtualdesigns_locations_locations')->where('id', '=', $id)->first();

        return $this->corsJson($location, 200);
    }

    public function destroy(Request $request, $id)
    {
        $this->assertApiKey($request);

        DB::table('virtualdesigns_locations_locations')->where('id', '=', $id)->delete();

        return $this->corsJson('Location Deleted', 200);
    }

    public function serp(Request $request)
    {
        $this->assertApiKey($request);

        $props = DB::table('virtualdesigns_properties_properties')
            ->where('is_live', '=', 1)
            ->whereNull('deleted_at')
            ->select('country_id', 'province_id', 'city_id', 'suburb_id')
            ->get();

        $locIds = [];
        foreach ($props as $prop) {
            foreach (['country_id', 'province_id', 'city_id', 'suburb_id'] as $field) {
                $id = $prop->$field ?? null;
                if ($id !== null && !in_array($id, $locIds, true)) {
                    $locIds[] = $id;
                }
            }
        }

        $locations = DB::table('virtualdesigns_locations_locations')
            ->whereIn('id', $locIds)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return $this->corsJson($locations, 200);
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
