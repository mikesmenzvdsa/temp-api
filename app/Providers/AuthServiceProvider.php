<?php

namespace App\Providers;

use App\Auth\OctoberUserProvider;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('viewPulse', function ($user = null) {
            if ($user === null || !isset($user->id)) {
                return false;
            }

            $groupId = DB::table('users_groups')->where('user_id', '=', $user->id)->value('user_group_id');

            return (int) $groupId === 2;
        });

        Auth::provider('october', function ($app, array $config) {
            return new OctoberUserProvider($app['hash'], $config['model']);
        });
    }
}
