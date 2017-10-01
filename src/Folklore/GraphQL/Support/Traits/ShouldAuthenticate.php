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

    public function authGuard()
    {
        return config('graphql.auth_guard');
    }

    public function loadUserIfPresent()
    {
        if ($this->getAuthManager()->guard($this->authGuard())->check()) {
            $this->getAuthManager()->shouldUse($this->authGuard());
        }
    }

    protected function checkAuthentication()
    {
        if (call_user_func([$this, 'requiresAuthentication']) === true) {
            if (!$this->getAuthManager()->guard($this->authGuard())->check()) {
                throw new AuthenticationException('Unauthenticated');
            }
        }
    }
}
