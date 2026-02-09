<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsersController extends Controller
{
    public function authenticateUser(Request $request)
    {
        $this->assertApiKey($request);

        $authHeader = $request->header('auth');
        if ($authHeader === null || !str_contains($authHeader, ':')) {
            return $this->corsJson(['code' => 400, 'message' => 'Missing auth header'], 400);
        }

        [$login, $password] = explode(':', $authHeader, 2);

        $user = DB::table('users')->where('email', '=', $login)->first();
        if (!$user) {
            return $this->corsJson(['code' => 404, 'message' => 'Wrong Username'], 404);
        }

        if (!Hash::check($password, $user->password)) {
            return $this->corsJson(['code' => 401, 'message' => 'Wrong Password'], 401);
        }

        $groups = DB::table('users_groups')
            ->where('user_id', '=', $user->id)
            ->select('user_group_id as id')
            ->get();

        $clientInfo = DB::table('virtualdesigns_clientinformation_')
            ->where('user_id', '=', $user->id)
            ->first();

        $userArray = (array) $user;
        $userArray['user_token'] = $user->password;
        $userArray['groups'] = $groups;
        $userArray['info'] = $clientInfo;
        if ($clientInfo && isset($clientInfo->contact_number)) {
            $userArray['contact_number'] = $clientInfo->contact_number;
        }

        return $this->corsJson($userArray, 200);
    }

    public function index(Request $request)
    {
        $this->assertApiKey($request);

        $groupId = $request->header('groupid');
        $limited = $request->header('limited');

        $query = DB::table('users')
            ->leftJoin('virtualdesigns_clientinformation_ as user_info', 'users.id', '=', 'user_info.user_id')
            ->join('users_groups as user_group', 'users.id', '=', 'user_group.user_id');

        if ($groupId !== null) {
            $query->where('user_group.user_group_id', '=', $groupId);
        }

        if ($limited === 'true' || $limited === true) {
            $query->select('users.id', 'users.name', 'users.surname');
        } else {
            $query->select('users.*', 'user_info.contact_number as phone', 'user_group.user_group_id');
        }

        $users = $query->get()->unique('id')->values();

        if ($users->isNotEmpty()) {
            $bodyCorpUsers = DB::table('virtualdesigns_bodycorp_bodycorp')
                ->whereNull('deleted_at')
                ->get();
            $users[0]->body_corporate_users = $bodyCorpUsers;
        }

        return $this->corsJson($users, 200);
    }

    public function show(Request $request, $id)
    {
        $this->assertApiKey($request);

        $user = DB::table('users')->where('id', '=', $id)->first();
        if (!$user) {
            return $this->corsJson(['code' => 404, 'message' => 'User not found'], 404);
        }

        $groups = DB::table('users_groups')
            ->where('user_id', '=', $user->id)
            ->select('user_group_id as id')
            ->get();

        $clientInfo = DB::table('virtualdesigns_clientinformation_')
            ->where('user_id', '=', $user->id)
            ->first();

        $userArray = (array) $user;
        $userArray['groups'] = $groups;
        $userArray['info'] = $clientInfo;

        return $this->corsJson($userArray, 200);
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