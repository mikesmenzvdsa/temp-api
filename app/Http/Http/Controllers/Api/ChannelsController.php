<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChannelsController extends Controller
{
    public function index(Request $request)
    {
        $records = DB::table('virtualdesigns_channels_providers')->get();

        return $this->corsJson($records, 200);
    }

    public function show(Request $request, $id)
    {
        $this->assertApiKey($request);

        $record = DB::table('virtualdesigns_channels_providers')
            ->where('id', '=', $id)
            ->get();

        return $this->corsJson($record, 200);
    }

    public function store(Request $request)
    {
        $this->assertApiKey($request);

        DB::table('virtualdesigns_channels_providers')->insert([
            'name' => $request->input('name'),
            'icon_path' => $request->input('icon_path'),
            'percentage' => $request->input('percentage'),
        ]);

        return $this->corsJson(['success' => true], 200);
    }

    public function update(Request $request, $id)
    {
        $this->assertApiKey($request);

        $existing = DB::table('virtualdesigns_channels_providers')->where('id', '=', $id)->first();
        if (!$existing) {
            return $this->corsJson(['code' => 404, 'message' => 'Channel not found'], 404);
        }

        DB::table('virtualdesigns_channels_providers')->where('id', '=', $id)->update([
            'name' => $request->input('name'),
            'icon_path' => $request->input('icon_path'),
            'percentage' => $request->input('percentage'),
        ]);

        return $this->corsJson(['success' => true], 200);
    }

    public function destroy(Request $request, $id)
    {
        $this->assertApiKey($request);

        DB::table('virtualdesigns_channels_providers')->where('id', '=', $id)->delete();

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
