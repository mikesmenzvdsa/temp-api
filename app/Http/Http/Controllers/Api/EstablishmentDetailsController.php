<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UploadService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class EstablishmentDetailsController extends Controller
{
    private UploadService $uploads;

    public function __construct(UploadService $uploads)
    {
        $this->uploads = $uploads;
    }

    public function getDetails(Request $request, $id)
    {
        $property = DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->first();
        if (!$property) {
            return $this->corsJson(['error' => 'Property not found'], 404);
        }

        $rooms = DB::table('virtualdesigns_rooms_rooms')->where('property_id', '=', $id)->get();
        $locations = DB::table('virtualdesigns_locations_locations_properties')
            ->where('property_id', '=', $id)
            ->pluck('location_id');
        $specials = DB::table('virtualdesigns_specials_specials')->where('property_id', '=', $id)->get();
        $seasons = DB::table('virtualdesigns_ratesseasons_seasons')->where('property_id', '=', $id)->get();
        $rates = DB::table('virtualdesigns_ratesseasons_rates')->where('property_id', '=', $id)->get();
        $features = DB::table('virtualdesigns_features_features_properties')
            ->where('property_id', '=', $id)
            ->pluck('feature_id');

        $attachments = [
            'image_gallery' => $this->uploads->getAttachmentFiles($this->propertyAttachmentType(), (int) $id, 'image_gallery'),
            'key_gallery' => $this->uploads->getAttachmentFiles($this->propertyAttachmentType(), (int) $id, 'key_gallery'),
            'linen_order_number_gallery' => $this->uploads->getAttachmentFiles($this->propertyAttachmentType(), (int) $id, 'linen_order_number_gallery'),
            'linen_invoice_number_gallery' => $this->uploads->getAttachmentFiles($this->propertyAttachmentType(), (int) $id, 'linen_invoice_number_gallery'),
        ];

        return $this->corsJson([
            'property' => $property,
            'rooms' => $rooms,
            'locations' => $locations,
            'special' => $specials,
            'season' => $seasons,
            'rate' => $rates,
            'feature' => $features,
            'attachments' => $attachments,
        ], 200);
    }

    public function getFeatures(Request $request, $id)
    {
        $featureIds = DB::table('virtualdesigns_features_features_properties')
            ->where('property_id', '=', $id)
            ->pluck('feature_id');

        return $this->corsJson($featureIds, 200);
    }

    public function updateFeatures(Request $request, $id)
    {
        $features = $request->input('selected_features', null);
        $isBb = false;
        if ($features === null) {
            $features = $request->input('selected_features_bb', []);
            $isBb = true;
        }

        $features = is_array($features) ? $features : [];
        $existing = DB::table('virtualdesigns_features_features_properties')
            ->where('property_id', '=', $id)
            ->pluck('feature_id')
            ->toArray();

        if ($isBb) {
            $features = array_values(array_unique(array_merge($existing, $features)));
        }

        DB::table('virtualdesigns_features_features_properties')->where('property_id', '=', $id)->delete();
        foreach ($features as $featureId) {
            DB::table('virtualdesigns_features_features_properties')->insert([
                'property_id' => $id,
                'feature_id' => $featureId,
            ]);
        }

        return $this->corsJson(['success' => true], 200);
    }

    public function getPhotos(Request $request, $id = null)
    {
        if ($id === null) {
            $properties = DB::table('virtualdesigns_properties_properties')->where('is_live', '=', 1)->get();
            $photos = [];
            foreach ($properties as $property) {
                $photos[$property->id] = $this->uploads->getAttachmentFiles(
                    $this->propertyAttachmentType(),
                    (int) $property->id,
                    'image_gallery'
                );
            }

            return $this->corsJson($photos, 200);
        }

        $photos = $this->uploads->getAttachmentFiles($this->propertyAttachmentType(), (int) $id, 'image_gallery');

        return $this->corsJson($photos, 200);
    }

    public function downloadPhoto($id)
    {
        $record = $this->uploads->getFileRecord((int) $id);
        if (!$record) {
            return $this->corsJson(['error' => 'File not found'], 404);
        }

        $path = Storage::disk('public')->path($record->disk_name);

        return response()->download($path, $record->file_name, [
            'Content-Type' => $record->content_type,
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function uploadPhotos(Request $request, $id)
    {
        $files = [];
        $uploads = $request->file('property_photos', []);
        if (!is_array($uploads)) {
            $uploads = [$uploads];
        }

        $existing = DB::table('system_files')
            ->where('attachment_type', '=', $this->propertyAttachmentType())
            ->where('attachment_id', '=', $id)
            ->where('field', '=', 'image_gallery')
            ->max('sort_order');
        $sortOrder = $existing ? (int) $existing + 1 : 1;

        foreach ($uploads as $upload) {
            if ($upload) {
                $files[] = $this->uploads->storeAttachment(
                    $upload,
                    $this->propertyAttachmentType(),
                    (int) $id,
                    'image_gallery',
                    $sortOrder
                );
                $sortOrder++;
            }
        }

        return $this->corsJson($files, 200);
    }

    public function updatePhotos(Request $request, $id)
    {
        foreach ($request->except('change_user') as $photo) {
            if (isset($photo['id'])) {
                $this->uploads->updateFileMetadata((int) $photo['id'], $photo);
            }
        }

        $photos = $this->uploads->getAttachmentFiles($this->propertyAttachmentType(), (int) $id, 'image_gallery');

        return $this->corsJson($photos, 200);
    }

    public function deletePhoto($id)
    {
        $record = $this->uploads->getFileRecord((int) $id);
        if (!$record) {
            return $this->corsJson(['error' => 'File not found'], 404);
        }

        $propertyId = (int) $record->attachment_id;
        $this->uploads->deleteFile((int) $id);

        $this->resequenceSortOrder($this->propertyAttachmentType(), $propertyId, 'image_gallery');

        $photos = $this->uploads->getAttachmentFiles($this->propertyAttachmentType(), $propertyId, 'image_gallery');

        return $this->corsJson($photos, 200);
    }

    public function getChannels($companyId)
    {
        $columns = [
            'air_bnb',
            'air_bnb_username',
            'air_bnb_password',
            'air_bnb_url',
            'booking_com',
            'booking_com_username',
            'booking_com_password',
            'booking_com_url',
            'book_now',
            'book_now_username',
            'book_now_password',
            'book_now_url',
            'travelground',
            'travelground_username',
            'travelground_password',
            'travelground_url',
            'holiday_apartments',
            'ha_username',
            'ha_password',
            'ha_url',
            'sa_venues',
            'sa_venues_username',
            'sa_venues_password',
            'sa_venues_url',
            'lekkeslaap',
            'lekkaslaap_username',
            'lekkaslaap_password',
            'lekkaslaap_url',
            'sleeping_out',
            'sleeping_out_username',
            'sleeping_out_password',
            'sleeping_out_url',
            'expedia',
            'expedia_username',
            'expedia_password',
            'expedia_url',
            'afristay',
            'afristay_username',
            'afristay_password',
            'afristay_url',
            'agoda',
            'agoda_username',
            'agoda_password',
            'agoda_url',
            'tripadvisor',
            'tripadvisor_username',
            'tripadvisor_password',
            'tripadvisor_url',
            'safari_now',
            'sn_username',
            'sn_password',
            'sn_url',
        ];

        $property = DB::table('virtualdesigns_properties_properties')->where('id', '=', $companyId)->select($columns)->first();

        return $this->corsJson($property, 200);
    }

    public function updateChannels(Request $request, $companyId)
    {
        $payload = $request->all();
        $data = [];
        foreach ((array) $payload as $group) {
            if (is_array($group)) {
                foreach ($group as $attr => $val) {
                    $data[$attr] = $val;
                }
            }
        }

        if (!empty($data)) {
            DB::table('virtualdesigns_properties_properties')->where('id', '=', $companyId)->update($data);
        }

        return $this->corsJson(['success' => true], 200);
    }

    public function getSpecials($propertyId)
    {
        $specials = DB::table('virtualdesigns_specials_specials')->where('property_id', '=', $propertyId)->get();

        return $this->corsJson($specials, 200);
    }

    public function createSpecial(Request $request, $propertyId)
    {
        $data = $request->all();
        $data['property_id'] = $propertyId;
        if (isset($data['name'])) {
            $data['slug'] = Str::slug((string) $data['name']);
        }

        $id = DB::table('virtualdesigns_specials_specials')->insertGetId($data);
        $special = DB::table('virtualdesigns_specials_specials')->where('id', '=', $id)->first();

        return $this->corsJson($special, 200);
    }

    public function updateSpecial(Request $request, $id)
    {
        $data = $request->all();
        if (isset($data['name'])) {
            $data['slug'] = Str::slug((string) $data['name']);
        }

        DB::table('virtualdesigns_specials_specials')->where('id', '=', $id)->update($data);

        return $this->corsJson(['success' => true], 200);
    }

    public function deleteSpecial($id)
    {
        DB::table('virtualdesigns_specials_specials')->where('id', '=', $id)->delete();

        return $this->corsJson(['success' => true], 200);
    }

    public function getDocuments($id)
    {
        $opInfo = $this->getOrCreateOperationalInfo($id);

        $corporate = $this->uploads->getAttachmentFiles(
            $this->operationalInfoAttachmentType(),
            (int) $opInfo->id,
            'doc_order'
        );
        $other = $this->uploads->getAttachmentFiles(
            $this->operationalInfoAttachmentType(),
            (int) $opInfo->id,
            'other_doc_order'
        );

        return $this->corsJson([
            'corporate_documents' => $corporate,
            'other_documents' => $other,
        ], 200);
    }

    public function uploadDocuments(Request $request, $id)
    {
        $opInfo = $this->getOrCreateOperationalInfo($id);
        $documentType = $request->input('document_type');

        if (!in_array($documentType, ['corporate_documents', 'other_documents'], true)) {
            return $this->corsJson(['error' => 'Invalid document_type'], 400);
        }

        $field = $documentType === 'corporate_documents' ? 'doc_order' : 'other_doc_order';

        $uploads = $request->file($documentType, []);
        if (!is_array($uploads)) {
            $uploads = [$uploads];
        }

        $existing = DB::table('system_files')
            ->where('attachment_type', '=', $this->operationalInfoAttachmentType())
            ->where('attachment_id', '=', $opInfo->id)
            ->where('field', '=', $field)
            ->max('sort_order');
        $sortOrder = $existing ? (int) $existing + 1 : 1;

        $files = [];
        foreach ($uploads as $upload) {
            if ($upload) {
                $files[] = $this->uploads->storeAttachment(
                    $upload,
                    $this->operationalInfoAttachmentType(),
                    (int) $opInfo->id,
                    $field,
                    $sortOrder
                );
                $sortOrder++;
            }
        }

        return $this->corsJson([
            'documents_type' => $documentType,
            'files' => $files,
        ], 200);
    }

    public function downloadDocument($id)
    {
        $record = $this->uploads->getFileRecord((int) $id);
        if (!$record) {
            return $this->corsJson(['error' => 'File not found'], 404);
        }

        $path = Storage::disk('public')->path($record->disk_name);

        return response()->download($path, $record->file_name, [
            'Content-Type' => $record->content_type,
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function updateDocuments(Request $request, $id)
    {
        foreach ($request->except('change_user') as $document) {
            if (isset($document['id'])) {
                $this->uploads->updateFileMetadata((int) $document['id'], $document);
            }
        }

        return $this->corsJson(['success' => true], 200);
    }

    public function deleteDocument($id)
    {
        $record = $this->uploads->getFileRecord((int) $id);
        if (!$record) {
            return $this->corsJson(['error' => 'File not found'], 404);
        }

        $opInfoId = (int) $record->attachment_id;
        $field = $record->field;

        $this->uploads->deleteFile((int) $id);
        $this->resequenceSortOrder($this->operationalInfoAttachmentType(), $opInfoId, $field);

        return $this->corsJson(['success' => true], 200);
    }

    public function getPropTableInfo(Request $request)
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $value) {
            $headers[strtolower($name)] = $value[0] ?? null;
        }

        $propertyQuery = DB::table('virtualdesigns_properties_properties')
            ->whereNull('virtualdesigns_properties_properties.deleted_at')
            ->where('is_live', '=', 1);

        if (!empty($headers['propertyname'])) {
            $propertyQuery->where('virtualdesigns_properties_properties.name', '=', $headers['propertyname']);
        }
        if (!empty($headers['suburbname'])) {
            $propertyQuery->where('suburb.name', '=', $headers['suburbname']);
        }
        if (!empty($headers['numberbedrooms'])) {
            $propertyQuery->where('virtualdesigns_properties_properties.bedroom_num', '=', $headers['numberbedrooms']);
        }
        if (!empty($headers['portfoliomanager'])) {
            $propertyQuery->where('virtualdesigns_properties_properties.portfolio_manager_id', '=', $headers['portfoliomanager']);
        }
        if (!empty($headers['propertyhost'])) {
            $propertyQuery->where('virtualdesigns_properties_properties.user_id', '=', $headers['propertyhost']);
        }

        $propertyData = $propertyQuery
            ->leftJoin('virtualdesigns_propertymanagerfees_propertymanagerfees as manager_fees', 'virtualdesigns_properties_properties.id', '=', 'manager_fees.property_id')
            ->leftJoin('virtualdesigns_extracharges_extracharges as extra_fees', 'virtualdesigns_properties_properties.id', '=', 'extra_fees.property_id')
            ->leftJoin('virtualdesigns_locations_locations as suburb', 'virtualdesigns_properties_properties.suburb_id', '=', 'suburb.id')
            ->leftJoin('users as portfolio_manager', 'virtualdesigns_properties_properties.portfolio_manager_id', '=', 'portfolio_manager.id')
            ->leftJoin('users as property_host', 'virtualdesigns_properties_properties.user_id', '=', 'property_host.id')
            ->select(
                'virtualdesigns_properties_properties.id as property_id',
                'virtualdesigns_properties_properties.name as property_name',
                'virtualdesigns_properties_properties.country_id as country_id',
                'virtualdesigns_properties_properties.bedroom_num as number_bedrooms',
                'suburb.name as suburb_name',
                'extra_fees.levy as levy_percentage',
                'virtualdesigns_properties_properties.comm_percent as comm_percent',
                'virtualdesigns_properties_properties.booking_fee as booking_fee',
                'virtualdesigns_properties_properties.clean_fee as clean_fee',
                'extra_fees.arrival_clean as extra_fees_arrival_clean',
                'extra_fees.departure_clean as extra_fees_departure_clean',
                'extra_fees.basic_housekeeping as extra_fees_basic_housekeeping',
                'extra_fees.mid_stay_clean as extra_fees_mid_stay_clean',
                'extra_fees.concierge_fee_arrival as extra_fees_concierge_fee_arrival',
                'extra_fees.welcome_pack as extra_fees_welcome_pack',
                'extra_fees.fanote_prices as extra_fees_fanote_prices',
                'extra_fees.wifi_costs as extra_fees_wifi_costs',
                'extra_fees.dstv_costs as extra_fees_dstv_costs',
                'extra_fees.netflix_costs as extra_fees_netflix_costs',
                'manager_fees.arrival_clean as manager_fees_arrival_clean',
                'manager_fees.departure_clean as manager_fees_departure_clean',
                'manager_fees.basic_housekeeping as manager_fees_basic_housekeeping',
                'manager_fees.mid_stay_clean as manager_fees_mid_stay_clean',
                'manager_fees.concierge_fee_arrival as manager_fees_concierge_fee_arrival',
                'manager_fees.welcome_pack as manager_fees_welcome_pack',
                'portfolio_manager.name as portfolio_manager_name',
                'portfolio_manager.surname as portfolio_manager_surname',
                'property_host.name as property_host_name',
                'property_host.surname as property_host_surname'
            )
            ->get()
            ->unique('property_id')
            ->values();

        $properties = DB::table('virtualdesigns_properties_properties')
            ->whereNull('virtualdesigns_properties_properties.deleted_at')
            ->where('is_live', '=', 1)
            ->select('id as property_id', 'name as property_name')
            ->get();

        $suburbs = DB::table('virtualdesigns_locations_locations')
            ->where('location_type', '=', 'Suburb')
            ->whereNull('deleted_at')
            ->select('id', 'name')
            ->get();

        $portfolioManagers = DB::table('users')
            ->leftJoin('users_groups as group', 'users.id', '=', 'group.user_id')
            ->where('group.user_group_id', '=', 2)
            ->select('users.id as id', 'users.name', 'users.surname')
            ->get()
            ->unique();

        $pmFinal = [];
        foreach ($portfolioManagers as $portfolioManager) {
            $pmFinal[$portfolioManager->id] = [
                'id' => (string) $portfolioManager->id,
                'name' => trim($portfolioManager->name . ' ' . $portfolioManager->surname),
            ];
        }

        $propertyHosts = DB::table('users')
            ->leftJoin('users_groups as group', 'users.id', '=', 'group.user_id')
            ->where('group.user_group_id', '=', 3)
            ->select('users.id as id', 'users.name', 'users.surname')
            ->get()
            ->unique();

        $phFinal = [];
        foreach ($propertyHosts as $propertyHost) {
            $phFinal[] = [
                'id' => (string) $propertyHost->id,
                'name' => trim($propertyHost->name . ' ' . $propertyHost->surname),
            ];
        }

        return $this->corsJson([
            'property_data' => $propertyData,
            'properties' => $properties,
            'suburbs' => $suburbs,
            'portfolio_managers' => $pmFinal,
            'property_hosts' => $phFinal,
        ], 200);
    }

    public function updatePropTableInfo(Request $request, $id)
    {
        $payload = $request->all();

        DB::table('virtualdesigns_properties_properties')->where('id', '=', $id)->update([
            'comm_percent' => $payload['comm_percent'] ?? null,
            'booking_fee' => $payload['booking_fee'] ?? null,
            'clean_fee' => $payload['clean_fee'] ?? null,
        ]);

        DB::table('virtualdesigns_extracharges_extracharges')->where('property_id', '=', $id)->update([
            'levy' => $payload['levy_percentage'] ?? null,
            'arrival_clean' => $payload['extra_fees_arrival_clean'] ?? null,
            'departure_clean' => $payload['extra_fees_departure_clean'] ?? null,
            'basic_housekeeping' => $payload['extra_fees_basic_housekeeping'] ?? null,
            'mid_stay_clean' => $payload['extra_fees_mid_stay_clean'] ?? null,
            'concierge_fee_arrival' => $payload['extra_fees_concierge_fee_arrival'] ?? null,
            'welcome_pack' => $payload['extra_fees_welcome_pack'] ?? null,
            'fanote_prices' => $payload['extra_fees_fanote_prices'] ?? null,
            'wifi_costs' => $payload['extra_fees_wifi_costs'] ?? null,
            'dstv_costs' => $payload['extra_fees_dstv_costs'] ?? null,
            'netflix_costs' => $payload['extra_fees_netflix_costs'] ?? null,
        ]);

        DB::table('virtualdesigns_propertymanagerfees_propertymanagerfees')->where('property_id', '=', $id)->update([
            'arrival_clean' => $payload['manager_fees_arrival_clean'] ?? null,
            'departure_clean' => $payload['manager_fees_departure_clean'] ?? null,
            'basic_housekeeping' => $payload['manager_fees_basic_housekeeping'] ?? null,
            'mid_stay_clean' => $payload['manager_fees_mid_stay_clean'] ?? null,
            'concierge_fee_arrival' => $payload['manager_fees_concierge_fee_arrival'] ?? null,
            'welcome_pack' => $payload['manager_fees_welcome_pack'] ?? null,
        ]);

        return $this->getPropTableInfo($request);
    }

    private function propertyAttachmentType(): string
    {
        return 'VirtualDesigns\\Properties\\Models\\Property';
    }

    private function operationalInfoAttachmentType(): string
    {
        return 'VirtualDesigns\\OperationalInformation\\Models\\OperationalInformation';
    }

    private function getOrCreateOperationalInfo($propertyId): object
    {
        $opInfo = DB::table('virtualdesigns_operationalinformation_operationalinformation')
            ->where('property_id', '=', $propertyId)
            ->first();

        if ($opInfo) {
            return $opInfo;
        }

        $id = DB::table('virtualdesigns_operationalinformation_operationalinformation')->insertGetId([
            'property_id' => $propertyId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('virtualdesigns_operationalinformation_operationalinformation')
            ->where('id', '=', $id)
            ->first();
    }

    private function resequenceSortOrder(string $attachmentType, int $attachmentId, string $field): void
    {
        $files = DB::table('system_files')
            ->where('attachment_type', '=', $attachmentType)
            ->where('attachment_id', '=', $attachmentId)
            ->where('field', '=', $field)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $index = 1;
        foreach ($files as $file) {
            DB::table('system_files')->where('id', '=', $file->id)->update([
                'sort_order' => $index,
                'updated_at' => now(),
            ]);
            $index++;
        }
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
