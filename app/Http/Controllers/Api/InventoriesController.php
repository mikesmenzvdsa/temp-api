<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InventoriesController extends Controller
{
    public function index(Request $request)
    {
        return $this->getInventory($request);
    }

    public function show(Request $request, int $id)
    {
        $request->merge(['invid' => $id]);

        return $this->getInventory($request);
    }

    public function createCategory(Request $request)
    {
        $this->assertApiKey($request);

        $now = now();
        $categoryId = DB::table('virtualdesigns_inventory_categories_new')->insertGetId([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $category = $this->getCategoryWithItems($categoryId);

        return $this->corsJson($category, 200);
    }

    public function updateCategory(Request $request)
    {
        $this->assertApiKey($request);

        $mode = $request->header('mode');
        $now = now();

        if ($mode === 'update') {
            DB::table('virtualdesigns_inventory_categories_new')
                ->where('id', '=', $request->input('id'))
                ->update([
                    'name' => $request->input('name'),
                    'description' => $request->input('description'),
                    'updated_at' => $now,
                ]);
        }

        if ($mode === 'delete') {
            DB::table('virtualdesigns_inventory_categories_new')
                ->where('id', '=', $request->input('id'))
                ->update(['deleted_at' => $now]);
        }

        $category = DB::table('virtualdesigns_inventory_categories_new')
            ->where('id', '=', $request->input('id'))
            ->get();

        return $this->corsJson($category, 200);
    }

    public function linkCategoryProperty(Request $request)
    {
        $this->assertApiKey($request);

        $now = now();
        $propertyId = (int) $request->input('property_id');
        $categories = $request->input('prop_categories', []);

        DB::table('virtualdesigns_inventory_property_categories_new')
            ->where('property_id', '=', $propertyId)
            ->delete();

        foreach ($categories as $categoryId) {
            DB::table('virtualdesigns_inventory_property_categories_new')->insert([
                'category_id' => $categoryId,
                'property_id' => $propertyId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $linked = DB::table('virtualdesigns_inventory_property_categories_new')
            ->where('property_id', '=', $propertyId)
            ->get();

        return $this->corsJson($linked, 200);
    }

    public function getCategory(Request $request)
    {
        $this->assertApiKey($request);

        $propId = $request->header('propid');
        $catId = $request->header('catid');
        $withItems = $request->header('get-with-items') !== null;

        $categories = DB::table('virtualdesigns_inventory_categories_new')
            ->whereNull('deleted_at');

        if ($propId) {
            $categories = $categories->whereIn('id', function ($query) use ($propId) {
                $query->select('category_id')
                    ->from('virtualdesigns_inventory_property_categories_new')
                    ->where('property_id', '=', $propId);
            });
        }

        if ($catId) {
            $categories = $categories->where('id', '=', $catId);
        }

        $categories = $categories->orderBy('name')->get();

        if ($withItems) {
            $categories = $categories->map(function ($category) {
                $category->items = $this->getItemsForCategory($category->id);
                return $category;
            });
        }

        return $this->corsJson($categories, 200);
    }

    public function createItem(Request $request)
    {
        $this->assertApiKey($request);

        $now = now();
        $itemId = DB::table('virtualdesigns_inventory_items_new')->insertGetId([
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->syncItemCategories($itemId, $request->input('categories', []), true);

        $item = $this->getItemWithCategories($itemId);

        return $this->corsJson($item, 200);
    }

    public function updateItem(Request $request)
    {
        $this->assertApiKey($request);

        $mode = $request->header('mode');
        $now = now();

        if ($mode === 'update') {
            DB::table('virtualdesigns_inventory_items_new')
                ->where('id', '=', $request->input('id'))
                ->update([
                    'name' => $request->input('name'),
                    'description' => $request->input('description'),
                    'updated_at' => $now,
                ]);

            $this->syncItemCategories((int) $request->input('id'), $request->input('categories', []), false);

            $item = $this->getItemWithCategories((int) $request->input('id'));

            return $this->corsJson($item, 200);
        }

        if ($mode === 'delete') {
            $deleted = DB::table('virtualdesigns_inventory_items_new')
                ->where('id', '=', $request->input('id'))
                ->update(['deleted_at' => $now]);

            return $this->corsJson(['success' => (bool) $deleted], 200);
        }

        return $this->corsJson(['error' => 'Invalid mode'], 422);
    }

    public function getItem(Request $request)
    {
        $this->assertApiKey($request);

        $itemId = $request->header('itemid');

        $items = DB::table('virtualdesigns_inventory_items_new')
            ->whereNull('deleted_at');

        if ($itemId) {
            $items = $items->where('id', '=', $itemId);
        }

        $items = $items->orderBy('name')->get()->map(function ($item) {
            $item->categories = $this->getCategoriesForItem($item->id);
            return $item;
        });

        return $this->corsJson($items, 200);
    }

    public function createInventoryLine(Request $request)
    {
        $this->assertApiKey($request);

        $now = now();
        $inventoryId = $request->input('inventory_id');
        $newInventory = null;

        if ($request->header('first') !== null) {
            $inventoryId = DB::table('virtualdesigns_property_inventories')->insertGetId([
                'property_id' => $request->input('property_id'),
                'is_locked' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $newInventory = DB::table('virtualdesigns_property_inventories')
                ->where('inventory_id', '=', $inventoryId)
                ->first();
        } else {
            DB::table('virtualdesigns_property_inventories')
                ->where('inventory_id', '=', $inventoryId)
                ->update(['updated_at' => $now]);
        }

        $imageUrl = $this->storeInventoryImage($request->file('item_photo'), $request->input('property_id'));
        if (!$imageUrl) {
            $existing = DB::table('virtualdesigns_inventory_inventories_new')->where('id', '=', $request->input('id'))->first();
            $imageUrl = $existing->image_url ?? null;
        }

        $lineId = DB::table('virtualdesigns_inventory_inventories_new')->insertGetId([
            'category_id' => $request->input('category_id'),
            'item_id' => $request->input('item_id'),
            'property_id' => $request->input('property_id'),
            'inventory_id' => $inventoryId,
            'qty' => $request->input('qty'),
            'description' => $request->input('description'),
            'image_url' => $imageUrl,
            'status' => $request->input('status'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $lineItem = $this->getSingleItem($lineId)->first();

        $data = $request->header('first') !== null
            ? ['line_item' => $lineItem, 'inventory' => $newInventory]
            : $lineItem;

        return $this->corsJson($data, 200);
    }

    public function updateInventoryLine(Request $request)
    {
        $this->assertApiKey($request);

        $mode = $request->header('mode');
        $now = now();

        if ($mode === 'update') {
            $imageUrl = $this->storeInventoryImage($request->file('item_photo'), $request->input('property_id'));
            if (!$imageUrl) {
                $existing = DB::table('virtualdesigns_inventory_inventories_new')->where('id', '=', $request->input('id'))->first();
                $imageUrl = $existing->image_url ?? null;
            }

            DB::table('virtualdesigns_inventory_inventories_new')
                ->where('id', '=', $request->input('id'))
                ->update([
                    'category_id' => $request->input('category_id'),
                    'item_id' => $request->input('item_id'),
                    'qty' => $request->input('qty'),
                    'description' => $request->input('description'),
                    'image_url' => $imageUrl,
                    'status' => $request->input('status'),
                    'updated_at' => $now,
                ]);

            DB::table('virtualdesigns_property_inventories')
                ->where('inventory_id', '=', $request->input('inventory_id'))
                ->update(['updated_at' => $now]);

            $record = $this->getSingleItem((int) $request->input('id'))->first();

            return $this->corsJson($record, 200);
        }

        if ($mode === 'delete') {
            DB::table('virtualdesigns_inventory_inventories_new')
                ->where('id', '=', $request->input('id'))
                ->update(['deleted_at' => $now]);

            DB::table('virtualdesigns_property_inventories')
                ->where('inventory_id', '=', $request->input('inventory_id'))
                ->update(['updated_at' => $now]);

            $record = DB::table('virtualdesigns_inventory_inventories_new')
                ->where('id', '=', $request->input('id'))
                ->first();

            return $this->corsJson($record, 200);
        }

        return $this->corsJson(['error' => 'Invalid mode'], 422);
    }

    public function updateInventory(Request $request)
    {
        $this->assertApiKey($request);

        $inventory = DB::table('virtualdesigns_property_inventories')
            ->where('inventory_id', '=', $request->input('id'))
            ->first();

        if (!$inventory) {
            return $this->corsJson(['error' => 'Inventory not found'], 404);
        }

        $newLocked = $inventory->is_locked ? 0 : 1;
        DB::table('virtualdesigns_property_inventories')
            ->where('inventory_id', '=', $request->input('id'))
            ->update(['is_locked' => $newLocked, 'updated_at' => now()]);

        $updated = DB::table('virtualdesigns_property_inventories')
            ->where('inventory_id', '=', $request->input('id'))
            ->first();

        return $this->corsJson($updated, 200);
    }

    public function deleteInventory(Request $request)
    {
        $this->assertApiKey($request);

        $now = now();
        $inventoryId = $request->input('inventory_id');

        DB::table('virtualdesigns_inventory_inventories_new')
            ->where('inventory_id', '=', $inventoryId)
            ->update(['deleted_at' => $now]);

        DB::table('virtualdesigns_property_inventories')
            ->where('inventory_id', '=', $inventoryId)
            ->update(['deleted_at' => $now]);

        $record = DB::table('virtualdesigns_property_inventories')
            ->where('inventory_id', '=', $inventoryId)
            ->first();

        return $this->corsJson($record, 200);
    }

    public function duplicateInventory(Request $request)
    {
        $this->assertApiKey($request);

        $inventoryId = $request->header('invid');
        $now = now();

        $oldInventory = DB::table('virtualdesigns_property_inventories')
            ->where('inventory_id', '=', $inventoryId)
            ->first();

        if (!$oldInventory) {
            return $this->corsJson(['error' => 'Inventory not found'], 404);
        }

        $newInventoryId = DB::table('virtualdesigns_property_inventories')->insertGetId([
            'property_id' => $oldInventory->property_id,
            'is_locked' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $items = DB::table('virtualdesigns_inventory_inventories_new')
            ->where('inventory_id', '=', $inventoryId)
            ->whereNull('deleted_at')
            ->get();

        foreach ($items as $item) {
            DB::table('virtualdesigns_inventory_inventories_new')->insert([
                'qty' => $item->qty,
                'status' => $item->status,
                'item_id' => $item->item_id,
                'image_url' => $item->image_url,
                'created_at' => $now,
                'updated_at' => $now,
                'category_id' => $item->category_id,
                'property_id' => $item->property_id,
                'description' => $item->description,
                'inventory_id' => $newInventoryId,
            ]);
        }

        $newInventory = DB::table('virtualdesigns_property_inventories')
            ->where('inventory_id', '=', $newInventoryId)
            ->first();

        return $this->corsJson($newInventory, 200);
    }

    public function getInventory(Request $request)
    {
        $this->assertApiKey($request);

        $propId = $request->header('propid') ?? $request->input('propid');
        $invId = $request->header('invid') ?? $request->input('invid');

        if ($propId) {
            $inventory = DB::table('virtualdesigns_property_inventories')
                ->where('property_id', '=', $propId)
                ->whereNull('deleted_at')
                ->get();

            return $this->corsJson($inventory, 200);
        }

        if ($invId) {
            $inventory = DB::table('virtualdesigns_inventory_inventories_new as inventory')
                ->where('inventory_id', '=', $invId)
                ->whereNull('inventory.deleted_at')
                ->leftJoin('virtualdesigns_inventory_categories_new as category', 'inventory.category_id', '=', 'category.id')
                ->leftJoin('virtualdesigns_inventory_items_new as item', 'inventory.item_id', '=', 'item.id')
                ->select('inventory.*', 'category.name as category_name', 'item.name')
                ->orderBy('inventory.created_at', 'desc')
                ->get();

            if ($request->input('print')) {
                if (!class_exists(\Dompdf\Dompdf::class)) {
                    return $this->corsJson(['error' => 'PDF generation not available'], 501);
                }

                $inventoryArray = $inventory->toArray();
                if (empty($inventoryArray)) {
                    return $this->corsJson(['error' => 'Inventory not found'], 404);
                }

                $inventoryId = $inventoryArray[0]->inventory_id;
                $mapped = array_map([$this, 'mapItems'], $inventoryArray);
                $inventoryUrl = $this->printInventory($mapped);

                DB::table('virtualdesigns_property_inventories')
                    ->where('inventory_id', '=', $inventoryId)
                    ->update(['inventory_url' => $inventoryUrl, 'updated_at' => now()]);

                return $this->corsJson(['file_url' => $inventoryUrl], 200);
            }

            return $this->corsJson($inventory, 200);
        }

        return $this->corsJson([], 200);
    }

    public function linkItemCategory(Request $request)
    {
        $this->assertApiKey($request);

        $itemId = (int) $request->input('item_id');
        $categories = $request->input('categories', []);

        $this->syncItemCategories($itemId, $categories, false);

        return $this->corsJson(['success' => true], 200);
    }

    private function syncItemCategories(int $itemId, array $categories, bool $isFirst): void
    {
        $now = now();

        if (!$isFirst) {
            DB::table('virtualdesigns_inventory_item_categories_new')
                ->where('item_id', '=', $itemId)
                ->delete();
        }

        foreach ($categories as $categoryId) {
            DB::table('virtualdesigns_inventory_item_categories_new')->insert([
                'category_id' => $categoryId,
                'item_id' => $itemId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function getItemsForCategory(int $categoryId)
    {
        $itemIds = DB::table('virtualdesigns_inventory_item_categories_new')
            ->where('category_id', '=', $categoryId)
            ->pluck('item_id');

        return DB::table('virtualdesigns_inventory_items_new')
            ->whereIn('id', $itemIds)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    private function getCategoriesForItem(int $itemId)
    {
        $categoryIds = DB::table('virtualdesigns_inventory_item_categories_new')
            ->where('item_id', '=', $itemId)
            ->pluck('category_id');

        return DB::table('virtualdesigns_inventory_categories_new')
            ->whereIn('id', $categoryIds)
            ->whereNull('deleted_at')
            ->orderBy('name')
            ->get();
    }

    private function getCategoryWithItems(int $categoryId)
    {
        $category = DB::table('virtualdesigns_inventory_categories_new')->where('id', '=', $categoryId)->first();
        if ($category) {
            $category->items = $this->getItemsForCategory($categoryId);
        }

        return $category;
    }

    private function getItemWithCategories(int $itemId)
    {
        $item = DB::table('virtualdesigns_inventory_items_new')->where('id', '=', $itemId)->first();
        if ($item) {
            $item->categories = $this->getCategoriesForItem($itemId);
        }

        return $item;
    }

    private function getSingleItem(int $id)
    {
        return DB::table('virtualdesigns_inventory_inventories_new as inventory')
            ->where('inventory.id', '=', $id)
            ->leftJoin('virtualdesigns_inventory_categories_new as category', 'inventory.category_id', '=', 'category.id')
            ->leftJoin('virtualdesigns_inventory_items_new as item', 'inventory.item_id', '=', 'item.id')
            ->select('inventory.*', 'category.name as category_name', 'item.name')
            ->get();
    }

    private function mapItems($item)
    {
        $imageUrl = $item->image_url ?? null;
        if (!$imageUrl) {
            return (array) $item;
        }

        $path = null;
        if (str_contains($imageUrl, '/storage/')) {
            $relative = ltrim(str_replace('/storage/', '', parse_url($imageUrl, PHP_URL_PATH) ?? ''), '/');
            if ($relative && Storage::disk('public')->exists($relative)) {
                $path = Storage::disk('public')->path($relative);
            }
        } elseif (is_file($imageUrl)) {
            $path = $imageUrl;
        }

        if (!$path || !is_file($path)) {
            return (array) $item;
        }

        $extension = mime_content_type($path);
        $imgBase64 = 'data:' . $extension . ';base64,' . base64_encode(file_get_contents($path));

        $itemArray = (array) $item;
        $itemArray['img_base64'] = $imgBase64;

        return $itemArray;
    }

    private function printInventory(array $data): ?string
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            return null;
        }

        $pdf = new \Dompdf\Dompdf();
        $inventoryId = ((object) $data[0])->inventory_id;
        $propertyId = ((object) $data[0])->property_id;
        $property = DB::table('virtualdesigns_properties_properties')->where('id', '=', $propertyId)->select('name')->first();

        $logoUrl = 'https://hostagents.co.za/documents/airagents-logo.png';
        $type = pathinfo($logoUrl, PATHINFO_EXTENSION);
        $imgData = @file_get_contents($logoUrl);
        $base64 = $imgData ? 'data:image/' . $type . ';base64,' . base64_encode($imgData) : '';

        $table = <<<HTML
            <div style="padding: 20px; background: #e80a89; margin-bottom: 25px; text-align: center;">
                <img src="{$base64}" alt="" />
            </div>
            <table boder='1' style="border: 1px solid; border-collapse: collapse; width: 100%;">
                <thead>
                    <tr style="border: 1px solid; background: #e80a89;">
                        <th style="border: 1px solid; padding: 5px; text-align: center; color: #fff;" colspan="7">{$property->name} - Inventory {$propertyId}-{$inventoryId}</th>
                    </tr>
                    <tr style="border: 1px solid; background: #e80a89; color: #fff;">
                        <th style="border: 1px solid; padding: 5px;">ID</th>
                        <th style="border: 1px solid; padding: 5px;">Inventory ID</th>
                        <th style="border: 1px solid; padding: 5px;">Category</th>
                        <th style="border: 1px solid; padding: 5px;">Item</th>
                        <th style="border: 1px solid; padding: 5px;">Qty</th>
                        <th style="border: 1px solid; padding: 5px;">Description</th>
                        <th style="border: 1px solid; padding: 5px;">Image</th>
                    </tr>
                </thead>
                <tbody>
        HTML;

        foreach ($data as $line) {
            $line = (object) $line;
            $table .= <<<HTML
                <tr boder='1'>
                    <td style="border: 1px solid; padding: 5px;"> {$line->id} </td>
                    <td style="border: 1px solid; padding: 5px;"> {$line->inventory_id} </td>
                    <td style="border: 1px solid; padding: 5px;"> {$line->category_name} </td>
                    <td style="border: 1px solid; padding: 5px;"> {$line->name} </td>
                    <td style="border: 1px solid; padding: 5px;"> {$line->qty} </td>
                    <td style="border: 1px solid; padding: 5px;"> {$line->description} </td>
                    <td style="border: 1px solid; padding: 5px;">
                        <img src="{$line->img_base64}" style="width: 75px"/>
                    </td>
                </tr>
            HTML;
        }

        $table .= <<<HTML
                </tbody>
            </table>
        HTML;

        $pdf->loadHtml($table);
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        $request = Request::capture();
        $protocol = $request->getScheme();
        $domain = $request->getHost();

        $fileToSave = base_path() . '/documents/' . $propertyId . '-' . $inventoryId . '.pdf';
        $fileUrl = $protocol . '://' . $domain . '/documents/' . $propertyId . '-' . $inventoryId . '.pdf';

        @file_put_contents($fileToSave, $pdf->output());

        return $fileUrl;
    }

    private function storeInventoryImage($file, ?int $propertyId): ?string
    {
        if (!$file) {
            return null;
        }

        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $filename = (string) Str::uuid() . ($extension ? '.' . $extension : '');
        $path = 'uploads/inventory/' . ($propertyId ?: 'general') . '/' . $filename;

        Storage::disk('public')->put($path, file_get_contents($file->getRealPath()));

        return Storage::disk('public')->url($path);
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
