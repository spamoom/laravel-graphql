<?php

namespace Folklore\GraphQL\Support\Traits;

use Illuminate\Contracts\Auth\Factory;
use Illuminate\Auth\AuthenticationException;

trait ShouldAuthenticate
{
    /**
     * Override this in your queries or mutations
     * to provide custom authentication
     */
    public function requiresAuthentication()
    {
        return config('graphql.auth_required', false);
    }

    protected function getAuthManager()
    {
        return app(Factory::class);
    }

    protected function checkAuthentication()
    {
        if (call_user_func([$this, 'requiresAuthentication']) === true) {
            $authGuard = config('graphql.auth_guard');

            if (!$this->getAuthManager()->guard($authGuard)->check()) {
                throw new AuthenticationException('Unauthenticated');
            }

            // User is present, we'll instruct the auth manager to use this guard
            $this->getAuthManager()->shouldUse($authGuard);
        }
    }
}
