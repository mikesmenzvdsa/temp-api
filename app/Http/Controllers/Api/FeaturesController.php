<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FeaturesController extends Controller
{
    public function index(Request $request)
    {
        $haFeatures = DB::table('virtualdesigns_features_features')
            ->whereNotNull('featuretype_id')
            ->where('is_room_type', '=', 0)
            ->whereNull('rentals_united_id')
            ->where('page_type', '!=', 'bnb')
            ->distinct()
            ->get();

        $ruFeatures = DB::table('virtualdesigns_features_features')
            ->where('is_room_type', '=', 0)
            ->whereNotNull('rentals_united_id')
            ->distinct()
            ->get();

        $allFeatures = DB::table('virtualdesigns_features_features')
            ->where('is_room_type', '=', 0)
            ->distinct()
            ->get()
            ->groupBy('featuretype_id');

        $features = [
            'ha_features' => $haFeatures,
            'ru_features' => $ruFeatures,
        ];

        foreach ($allFeatures as $key => $value) {
            $features[$key ?? 'unassigned'] = $value;
        }

        return $this->corsJson($features, 200);
    }

    public function show(Request $request, $id)
    {
        $feature = DB::table('virtualdesigns_features_features')->where('id', '=', $id)->first();
        if (!$feature) {
            return $this->corsJson(['message' => 'Record not found.'], 404);
        }

        $properties = DB::table('virtualdesigns_features_features_properties as fp')
            ->join('virtualdesigns_properties_properties as p', 'fp.property_id', '=', 'p.id')
            ->where('fp.feature_id', '=', $id)
            ->orderBy('p.name')
            ->select('p.*')
            ->get();

        $rooms = DB::table('virtualdesigns_features_features_rooms as fr')
            ->join('virtualdesigns_rooms_rooms as r', 'fr.room_id', '=', 'r.id')
            ->where('fr.feature_id', '=', $id)
            ->select('r.*')
            ->get();

        $featureType = null;
        if ($feature->featuretype_id !== null) {
            $featureType = DB::table('virtualdesigns_features_features')
                ->where('id', '=', $feature->featuretype_id)
                ->first();
        }

        $user = null;
        if ($feature->user_id !== null) {
            $user = DB::table('users')->where('id', '=', $feature->user_id)->first();
        }

        return $this->corsJson([
            'feature' => $feature,
            'properties' => $properties,
            'featuretype' => $featureType,
            'user' => $user,
            'rooms' => $rooms,
        ], 200);
    }

    public function store(Request $request)
    {
        $id = DB::table('virtualdesigns_features_features')->insertGetId([
            'name' => $request->input('name'),
            'slug' => Str::slug((string) $request->input('name')),
            'featuretype_id' => $request->input('featuretype_id'),
            'is_room_type' => $request->input('is_room_type'),
            'user_id' => $request->input('user_id'),
            'page_type' => $request->input('page_type'),
        ]);

        $feature = DB::table('virtualdesigns_features_features')->where('id', '=', $id)->first();

        return $this->corsJson(['feature' => $feature], 200);
    }

    public function update(Request $request, $id)
    {
        $feature = DB::table('virtualdesigns_features_features')->where('id', '=', $id)->first();
        if (!$feature) {
            return $this->corsJson(['message' => 'Record not found.'], 404);
        }

        DB::table('virtualdesigns_features_features')->where('id', '=', $id)->update([
            'name' => $request->input('name', $feature->name),
            'slug' => Str::slug((string) $request->input('name', $feature->name)),
            'featuretype_id' => $request->input('featuretype_id', $feature->featuretype_id),
            'is_room_type' => $request->input('is_room_type', $feature->is_room_type),
            'user_id' => $request->input('user_id', $feature->user_id),
            'page_type' => $request->input('page_type', $feature->page_type),
        ]);

        return $this->corsJson(['success' => true], 200);
    }

    public function destroy(Request $request, $id)
    {
        $feature = DB::table('virtualdesigns_features_features')->where('id', '=', $id)->first();
        if (!$feature) {
            return $this->corsJson(['message' => 'Record not found.'], 404);
        }

        DB::table('virtualdesigns_features_features')->where('id', '=', $id)->delete();

        return $this->corsJson(['success' => true], 200);
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
