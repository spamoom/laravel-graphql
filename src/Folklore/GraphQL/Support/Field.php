<?php

namespace Folklore\GraphQL\Support;

use Illuminate\Support\Fluent;
use Illuminate\Auth\AuthenticationException;
use Folklore\GraphQL\Error\AuthorizationError;

class Field extends Fluent
{

    /**
     * Override this in your queries or mutations
     * to provide custom authorization
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Override this in your queries or mutations
     * to provide custom authentication
     */
    public function requiresAuthentication()
    {
        return config('graphql.auth_required', false);
    }

    public function attributes()
    {
        return [];
    }

    public function type()
    {
        return null;
    }

    public function args()
    {
        return [];
    }

    protected function getResolver()
    {
        if (!method_exists($this, 'resolve')) {
            return null;
        }

        $this->checkAuthentication();

        $resolver = array($this, 'resolve');
        $authorize = [$this, 'authorize'];

        return function () use ($resolver, $authorize) {
            $args = func_get_args();

            // Authorize
            if (call_user_func($authorize) !== true) {
                throw new AuthorizationError('Unauthorized');
            }

            return call_user_func_array($resolver, $args);
        };
    }

    /**
     * Get the attributes from the container.
     *
     * @return array
     */
    public function getAttributes()
    {
        $attributes = $this->attributes();
        $args = $this->args();

        $attributes = array_merge($this->attributes, [
            'args' => $args
        ], $attributes);

        $type = $this->type();
        if (isset($type)) {
            $attributes['type'] = $type;
        }

        $resolver = $this->getResolver();
        if (isset($resolver)) {
            $attributes['resolve'] = $resolver;
        }

        return $attributes;
    }

    /**
     * Convert the Fluent instance to an array.
     *
     * @return array
     */
    public function toArray()
    {
        return $this->getAttributes();
    }

    /**
     * Dynamically retrieve the value of an attribute.
     *
     * @param  string  $key
     * @return mixed
     */
    public function __get($key)
    {
        $attributes = $this->getAttributes();
        return isset($attributes[$key]) ? $attributes[$key]:null;
    }

    /**
     * Dynamically check if an attribute is set.
     *
     * @param  string  $key
     * @return void
     */
    public function __isset($key)
    {
        $attributes = $this->getAttributes();
        return isset($attributes[$key]);
    }

    protected function checkAuthentication()
    {
        if (call_user_func([$this, 'requiresAuthentication']) !== true) {
            // Auth not required for this
            return;
        }

        $auth = app('Illuminate\Contracts\Auth\Factory');
        $authGuard = config('graphql.auth_guard');

        if (!$auth->guard($authGuard)->check()) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // User is present, we'll instruct the auth manager to use this guard
        $auth->shouldUse($authGuard);
    }
}
