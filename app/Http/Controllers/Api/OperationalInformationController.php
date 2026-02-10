<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UploadService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class OperationalInformationController extends Controller
{
    private string $table = 'virtualdesigns_operationalinformation_operationalinformation';

    public function getDetails(Request $request, int $propertyId)
    {
        $this->assertApiKey($request);

        $propertyInfo = DB::table($this->table)->where('property_id', '=', $propertyId)->first();

        if ($propertyInfo) {
            $propertyInfo->body_corporate_users = DB::table('virtualdesigns_bodycorp_bodycorp')
                ->whereNull('deleted_at')
                ->get();
        }

        return $this->corsJson(['property_info' => $propertyInfo], 200);
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

    public function getSuppliers(Request $request)
    {
        $this->assertApiKey($request);

        $cities = DB::table('virtualdesigns_locations_locations')->where('location_type', '=', 'City')->get();
        $suburbs = DB::table('virtualdesigns_locations_locations')->where('location_type', '=', 'Suburb')->get();
        $properties = DB::table('virtualdesigns_properties_properties')->whereNull('deleted_at')->get();
        $supplierTypes = DB::table('virtualdesigns_suppliertypes_suppliertypes_types')->whereNull('deleted_at')->get();

        $suppliers = $this->hydrateSuppliers(DB::table('virtualdesigns_suppliers_suppliers')
            ->whereNull('deleted_at')
            ->get());

        return $this->corsJson([
            'cities' => $cities,
            'suburbs' => $suburbs,
            'properties' => $properties,
            'suppliers' => $suppliers,
            'supplier_types' => $supplierTypes,
        ], 200);
    }

    public function updateSupplier(Request $request, int $id)
    {
        $this->assertApiKey($request);

        $payload = $this->filteredPayload($request, 'virtualdesigns_suppliers_suppliers');
        if (!empty($payload)) {
            DB::table('virtualdesigns_suppliers_suppliers')->where('id', '=', $id)->update($this->withUpdatedAt($payload, 'virtualdesigns_suppliers_suppliers'));
        }

        $supplier = DB::table('virtualdesigns_suppliers_suppliers')->where('id', '=', $id)->first();
        $hydrated = $supplier ? $this->hydrateSuppliers(collect([$supplier]))->first() : null;

        return $this->corsJson($hydrated, 200);
    }

    public function linkSupplier(Request $request, int $id)
    {
        $this->assertApiKey($request);

        $propertyId = (int) $request->input('property_id', 0);
        if ($propertyId <= 0) {
            return $this->corsJson(['error' => 'Property id is required'], 400);
        }

        $exists = DB::table('virtualdesigns_suppliers_suppliers_properties')
            ->where('suppliers_id', '=', $id)
            ->where('property_id', '=', $propertyId)
            ->exists();

        if ($exists) {
            DB::table('virtualdesigns_suppliers_suppliers_properties')
                ->where('suppliers_id', '=', $id)
                ->where('property_id', '=', $propertyId)
                ->delete();
        } else {
            DB::table('virtualdesigns_suppliers_suppliers_properties')->insert([
                'suppliers_id' => $id,
                'property_id' => $propertyId,
            ]);
        }

        $supplier = DB::table('virtualdesigns_suppliers_suppliers')->where('id', '=', $id)->first();
        $hydrated = $supplier ? $this->hydrateSuppliers(collect([$supplier]))->first() : null;

        return $this->corsJson($hydrated, 200);
    }

    public function getApprovedSuppliers(Request $request, int $propertyId)
    {
        $this->assertApiKey($request);

        $cities = DB::table('virtualdesigns_locations_locations')->where('location_type', '=', 'City')->get();
        $suburbs = DB::table('virtualdesigns_locations_locations')->where('location_type', '=', 'Suburb')->get();
        $properties = DB::table('virtualdesigns_properties_properties')->whereNull('deleted_at')->get();
        $supplierTypes = DB::table('virtualdesigns_suppliertypes_suppliertypes_types')->whereNull('deleted_at')->get();

        $supplierIds = DB::table('virtualdesigns_suppliers_suppliers_properties')
            ->where('property_id', '=', $propertyId)
            ->pluck('suppliers_id');

        $suppliers = $this->hydrateSuppliers(DB::table('virtualdesigns_suppliers_suppliers')
            ->whereIn('id', $supplierIds)
            ->whereNull('deleted_at')
            ->get());

        return $this->corsJson([
            'cities' => $cities,
            'suburbs' => $suburbs,
            'properties' => $properties,
            'suppliers' => $suppliers,
            'supplier_types' => $supplierTypes,
        ], 200);
    }

    public function getLinenInvoices(Request $request, int $propertyId)
    {
        $this->assertApiKey($request);

        $operationalInfo = DB::table($this->table)
            ->select(['id', 'linen_supplier_id', 'owner_linen', 'property_id', 'departure_linen', 'linen_pool'])
            ->where('property_id', '=', $propertyId)
            ->first();

        $linenInvoices = [];
        if ($operationalInfo) {
            $linenInvoices = (new UploadService())->getAttachmentFiles(
                'Virtualdesigns\\Operationalinformation\\Models\\Operationalinformation',
                $operationalInfo->id,
                'linen_order'
            );
        }

        $beds = DB::table('virtualdesigns_propertylinen_beds')->where('property_id', '=', $propertyId)->get();
        $linenSuppliers = DB::table('users')
            ->join('users_groups', 'users.id', '=', 'users_groups.user_id')
            ->where('users_groups.user_group_id', '=', 4)
            ->select('users.*')
            ->get();

        return $this->corsJson([
            'operational_info' => $operationalInfo,
            'linen_suppliers' => $linenSuppliers,
            'linen_invoices' => $linenInvoices,
            'beds' => $beds,
        ], 200);
    }

    public function uploadLinenInvoices(Request $request, int $propertyId)
    {
        $this->assertApiKey($request);

        $operationalInfo = DB::table($this->table)->where('property_id', '=', $propertyId)->first();
        if (!$operationalInfo) {
            $recordId = DB::table($this->table)->insertGetId($this->withCreatedAt([
                'property_id' => $propertyId,
            ], $this->table));
            $operationalInfo = DB::table($this->table)->where('id', '=', $recordId)->first();
        }

        $files = [];
        $uploads = $request->file('linen_invoices', []);
        $uploadService = new UploadService();

        foreach ($uploads as $file) {
            $files[] = $uploadService->storeAttachment(
                $file,
                'Virtualdesigns\\Operationalinformation\\Models\\Operationalinformation',
                (int) $operationalInfo->id,
                'linen_order'
            );
        }

        return $this->corsJson($files, 200);
    }

    public function updateLinenInvoiceDetails(Request $request, int $id)
    {
        $this->assertApiKey($request);

        $record = (new UploadService())->updateFileMetadata($id, $request->all());

        return $this->corsJson(['success' => true, 'record' => $record], 200);
    }

    public function downloadLinenInvoice(Request $request, int $id)
    {
        $this->assertApiKey($request);

        $record = DB::table('system_files')->where('id', '=', $id)->first();
        if (!$record) {
            return $this->corsJson(['error' => 'File not found'], 404);
        }

        $headers = [
            'Content-type' => $record->content_type,
            'Content-disposition' => "attachment; filename='" . $record->file_name . "';",
            'Access-Control-Allow-Origin' => '*',
        ];

        return response()->download(
            Storage::disk('public')->path($record->disk_name),
            $record->file_name,
            $headers
        );
    }

    public function deleteLinenInvoice(Request $request, int $id)
    {
        $this->assertApiKey($request);

        $deleted = (new UploadService())->deleteFile($id);

        return $this->corsJson(['success' => $deleted], 200);
    }

    public function getKeyImages(Request $request, int $propertyId)
    {
        $this->assertApiKey($request);

        $property = DB::table('virtualdesigns_properties_properties')
            ->where('id', '=', $propertyId)
            ->select(['id', 'key_notes'])
            ->first();

        $keys = (new UploadService())->getAttachmentFiles(
            'Virtualdesigns\\Properties\\Models\\Property',
            $propertyId,
            'key_gallery'
        );

        return $this->corsJson([
            'keys' => $keys,
            'property' => $property,
        ], 200);
    }

    public function uploadKeyImages(Request $request, int $propertyId)
    {
        $this->assertApiKey($request);

        $uploads = $request->file('key_images', []);
        if (empty($uploads)) {
            return $this->corsJson(['error' => 'No files provided'], 422);
        }

        $uploadService = new UploadService();
        $record = $uploadService->storeAttachment(
            $uploads[0],
            'Virtualdesigns\\Properties\\Models\\Property',
            $propertyId,
            'key_gallery',
            (int) $request->input('sort_order', 1)
        );

        $record = $uploadService->updateFileMetadata($record->id, [
            'description' => $request->input('description'),
            'sort_order' => $request->input('sort_order', 1),
        ]);

        return $this->corsJson($record, 200);
    }

    public function updateKeyDetails(Request $request, int $id)
    {
        $this->assertApiKey($request);

        $record = (new UploadService())->updateFileMetadata($id, $request->all());

        return $this->corsJson(['success' => true, 'record' => $record], 200);
    }

    public function deleteKeyImage(Request $request, int $id)
    {
        $this->assertApiKey($request);

        $deleted = (new UploadService())->deleteFile($id);

        return $this->corsJson(['success' => $deleted], 200);
    }

    public function addBed(Request $request, int $propertyId)
    {
        $this->assertApiKey($request);

        $payload = $this->filteredPayload($request, 'virtualdesigns_propertylinen_beds');
        $payload['property_id'] = $propertyId;
        $recordId = DB::table('virtualdesigns_propertylinen_beds')
            ->insertGetId($this->withCreatedAt($payload, 'virtualdesigns_propertylinen_beds'));

        $record = DB::table('virtualdesigns_propertylinen_beds')->where('id', '=', $recordId)->first();

        return $this->corsJson($record, 200);
    }

    public function updateBed(Request $request, int $id)
    {
        $this->assertApiKey($request);

        $payload = $this->filteredPayload($request, 'virtualdesigns_propertylinen_beds');
        if (!empty($payload)) {
            DB::table('virtualdesigns_propertylinen_beds')->where('id', '=', $id)->update($this->withUpdatedAt($payload, 'virtualdesigns_propertylinen_beds'));
        }

        $record = DB::table('virtualdesigns_propertylinen_beds')->where('id', '=', $id)->first();

        return $this->corsJson($record, 200);
    }

    public function deleteBed(Request $request, int $id)
    {
        $this->assertApiKey($request);

        DB::table('virtualdesigns_propertylinen_beds')->where('id', '=', $id)->delete();

        return $this->corsJson(['success' => true], 200);
    }

    private function hydrateSuppliers($suppliers)
    {
        $supplierTypes = DB::table('virtualdesigns_suppliertypes_suppliertypes_types')->whereNull('deleted_at')->get()->keyBy('id');
        $locations = DB::table('virtualdesigns_locations_locations')->get()->keyBy('id');
        $pivot = DB::table('virtualdesigns_suppliers_suppliers_properties')->get();

        return $suppliers->map(function ($supplier) use ($supplierTypes, $locations, $pivot) {
            $supplier->type = $supplierTypes->get($supplier->type_id);
            $supplier->city = $locations->get($supplier->city_id);
            $supplier->suburb = $locations->get($supplier->suburb_id);
            $supplier->properties = $pivot
                ->where('suppliers_id', '=', $supplier->id)
                ->pluck('property_id')
                ->values();

            return $supplier;
        });
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
