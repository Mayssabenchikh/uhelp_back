<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use App\Models\Group;
use App\Models\Conversation;
use App\Models\Attachment;
use App\Policies\GroupPolicy;
use App\Policies\ConversationPolicy;
use App\Policies\AttachmentPolicy;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Group::class => GroupPolicy::class,
        Conversation::class => ConversationPolicy::class,
        Attachment::class => AttachmentPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
