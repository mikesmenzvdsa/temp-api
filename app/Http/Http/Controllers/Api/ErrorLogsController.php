<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ErrorLogsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->assertApiKey($request);

        $errors = DB::table('virtualdesigns_hostagentsapi_errors')
            ->whereNull('deleted_at')
            ->get();

        return $this->corsJson($errors, 200);
    }

    public function show(Request $request, int $userid): JsonResponse
    {
        $this->assertApiKey($request);

        $errors = DB::table('virtualdesigns_hostagentsapi_errors')
            ->whereNull('deleted_at')
            ->get();

        return $this->corsJson($errors, 200);
    }

    public function store(Request $request): JsonResponse
    {
        $this->assertApiKey($request);

        $errorMessage = $request->input('error');
        $userName = $request->input('user');
        $userEmail = $request->input('user_email');
        $date = $request->input('date');
        $url = $request->input('url');

        $errorLog = DB::table('virtualdesigns_hostagentsapi_errors')->insert([
            'error' => $errorMessage,
            'user' => $userName,
            'user_email' => $userEmail,
            'date' => $date,
            'url' => $url,
            'created_at' => $date,
        ]);

        return $this->corsJson($errorLog, 200);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $this->assertApiKey($request);

        return $this->corsJson(['error' => 'Not implemented'], 501);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->assertApiKey($request);

        DB::table('virtualdesigns_hostagentsapi_errors')->where('id', '=', $id)->delete();

        return $this->corsJson('Record Deleted', 200);
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

    private function corsJson($data, int $status): JsonResponse
    {
        return response()
            ->json($data, $status)
            ->header('Content-Type', 'application.json')
            ->header('Access-Control-Allow-Origin', '*');
    }
}
