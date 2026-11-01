<?php

namespace App\Providers;

use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;
use OptimistDigital\NovaSettings\Events\SettingsSaved;
use App\Models\User;
use App\Notifications\UserSettingsUpdatedNotification;
use Illuminate\Support\Facades\Notification;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
    ];

    public function boot()
    {
        parent::boot();

        Event::listen(SettingsSaved::class, function ($event) {
            $user = auth()->user();
            
            if ($user) {
                // Nova-settings often nests data under 'settings' or passes them directly
                $allInputs = request()->all();
                $settingsData = $allInputs['settings'] ?? $allInputs;

                $name = $settingsData['name'] ?? request('name');
                $email = $settingsData['email'] ?? request('email');
                $avatar = $settingsData['avatar'] ?? request('avatar');

                $inputData = [];

                if (!empty($name)) {
                    $user->name = $name;
                    $inputData['username'] = $name;
                }

                if (!empty($email)) {
                    $user->email = $email;
                    $inputData['email'] = $email;
                }

                if (!empty($avatar)) {
                    $user->avatar = $avatar;
                    $inputData['animated_profile_picture'] = $avatar;
                }

                if (!empty($inputData)) {
                    $user->save();

                    // Safely query administrators supporting string roles or constants
                    $admins = User::where('role', 'Administrator')
                        ->orWhere('role', User::ROLE_ADMIN ?? 'admin')
                        ->get();

                    if ($admins->isNotEmpty()) {
                        \Illuminate\Support\Facades\Notification::send($admins, new \App\Notifications\UserSettingsUpdatedNotification($user, $inputData));
                    }
                }
            }
        });
    }
}