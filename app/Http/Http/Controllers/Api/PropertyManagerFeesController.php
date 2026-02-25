<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PropertyManagerFeesController extends Controller
{
    private string $table = 'virtualdesigns_propertymanagerfees_propertymanagerfees';

    public function index(Request $request)
    {
        $this->assertApiKey($request);

        $records = DB::table($this->table)->get();

        return $this->corsJson($records->first(), 200);
    }

    public function show(Request $request, int $propertyId)
    {
        $this->assertApiKey($request);

        $property = DB::table('virtualdesigns_properties_properties')->where('id', '=', $propertyId)->first();
        $fees = DB::table($this->table)->where('property_id', '=', $propertyId)->first();
        $rooms = DB::table('virtualdesigns_rooms_rooms')->where('property_id', '=', $propertyId)->get();

        return $this->corsJson([
            'property' => $property,
            'property_manager_fees' => $fees,
            'rooms' => $rooms,
        ], 200);
    }

    public function store(Request $request)
    {
        $this->assertApiKey($request);

        $payload = $this->filteredPayload($request, $this->table);
        $propertyId = (int) ($payload['property_id'] ?? $request->input('property_id', 0));

        if ($propertyId <= 0) {
            return $this->corsJson(['error' => 'Property id is required'], 400);
        }

        $payload['property_id'] = $propertyId;

        $existing = DB::table($this->table)->where('property_id', '=', $propertyId)->first();
        if ($existing) {
            DB::table($this->table)->where('id', '=', $existing->id)->update($this->withUpdatedAt($payload, $this->table));
            $record = DB::table($this->table)->where('id', '=', $existing->id)->first();
        } else {
            $recordId = DB::table($this->table)->insertGetId($this->withCreatedAt($payload, $this->table));
            $record = DB::table($this->table)->where('id', '=', $recordId)->first();
        }

        return $this->corsJson($record, 200);
    }

    public function update(Request $request, int $id)
    {
        $this->assertApiKey($request);

        $payload = $this->filteredPayload($request, $this->table);
        if (empty($payload)) {
            return $this->corsJson(['error' => 'No valid fields provided'], 422);
        }

        DB::table($this->table)->where('id', '=', $id)->update($this->withUpdatedAt($payload, $this->table));
        $record = DB::table($this->table)->where('id', '=', $id)->first();

        return $this->corsJson($record, 200);
    }

    private function filteredPayload(Request $request, string $table): array
    {
        $payload = $request->all();

        unset($payload['id'], $payload['created_at'], $payload['updated_at'], $payload['change_user'], $payload['_method']);

        $columns = Schema::getColumnListing($table);
        return array_intersect_key($payload, array_flip($columns));
    }

    private function withUpdatedAt(array $payload, string $table): array
    {
        if (in_array('updated_at', Schema::getColumnListing($table), true)) {
            $payload['updated_at'] = now();
        }

        return $payload;
    }

    private function withCreatedAt(array $payload, string $table): array
    {
        $columns = Schema::getColumnListing($table);
        if (in_array('created_at', $columns, true)) {
            $payload['created_at'] = now();
        }
        if (in_array('updated_at', $columns, true)) {
            $payload['updated_at'] = now();
        }

        return $payload;
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
