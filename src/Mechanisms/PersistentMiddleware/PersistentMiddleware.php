<?php

namespace Livewire\Mechanisms\PersistentMiddleware;

use GSManager\Routing\Router;
use Livewire\Mechanisms\Mechanism;
use function Livewire\on;
use GSManager\Support\Str;
use Livewire\Drawer\Utils;
use Livewire\Mechanisms\HandleRequests\HandleRequests;

class PersistentMiddleware extends Mechanism
{
    protected static $persistentMiddleware = [
        \GSManager\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        \GSManager\Jetstream\Http\Middleware\AuthenticateSession::class,
        \GSManager\Auth\Middleware\AuthenticateWithBasicAuth::class,
        \GSManager\Routing\Middleware\SubstituteBindings::class,
        \App\Http\Middleware\RedirectIfAuthenticated::class,
        \GSManager\Auth\Middleware\Authenticate::class,
        \GSManager\Auth\Middleware\Authorize::class,
        \App\Http\Middleware\Authenticate::class,
    ];

    protected $path;
    protected $method;

    function boot()
    {
        on('dehydrate', function ($component, $context) {
            [$path, $method] = $this->extractPathAndMethodFromRequest();

            $context->addMemo('path', $path);
            $context->addMemo('method', $method);
        });

        on('snapshot-verified', function ($snapshot) {
            // Only apply middleware to requests hitting the Livewire update endpoint, and not any fake requests such as a test.
            if (! app(HandleRequests::class)->isLivewireRoute()) return;

            $this->extractPathAndMethodFromSnapshot($snapshot);

            $this->applyPersistentMiddleware();
        });

        on('flush-state', function() {
            // Only flush these at the end of a full request, so that child components have access to this data.
            $this->path = null;
            $this->method = null;
        });
    }

    function addPersistentMiddleware($middleware)
    {
        static::$persistentMiddleware = Router::uniqueMiddleware(array_merge(static::$persistentMiddleware, (array) $middleware));
    }

    function setPersistentMiddleware($middleware)
    {
        static::$persistentMiddleware = Router::uniqueMiddleware((array) $middleware);
    }

    function getPersistentMiddleware()
    {
        return static::$persistentMiddleware;
    }

    protected function extractPathAndMethodFromRequest()
    {
        if (app(HandleRequests::class)->isLivewireRoute()) {
            return [$this->path, $this->method];
        }

        return [request()->path(), request()->method()];
    }

    protected function extractPathAndMethodFromSnapshot($snapshot)
    {
        if (
            ! isset($snapshot['memo']['path'])
            || ! isset($snapshot['memo']['method'])
        ) return;

        // Store these locally, so dynamically added child components can use this data.
        $this->path = $snapshot['memo']['path'];
        $this->method = $snapshot['memo']['method'];
    }

    protected function applyPersistentMiddleware()
    {
        $request = $this->makeFakeRequest();

        $middleware = $this->getApplicablePersistentMiddleware($request);

        // Only send through pipeline if there are middleware found
        if (is_null($middleware)) return;

        Utils::applyMiddleware($request, $middleware);
    }

    protected function makeFakeRequest()
    {
        $originalPath = $this->formatPath($this->path);
        $originalMethod = $this->method;

        $currentPath = $this->formatPath(request()->path());

        // Clone server bag to ensure changes below don't overwrite the original.
        $serverBag = clone request()->server;

        // Replace the Livewire endpoint path with the path from the original request.
        $serverBag->set(
            'REQUEST_URI',
            str_replace($currentPath, $originalPath, $serverBag->get('REQUEST_URI'))
        );

        $serverBag->set('REQUEST_METHOD', $originalMethod);

        /**
         * Make the fake request from the current request with path and method changed so
         * all other request data, such as headers, are available in the fake request,
         * but merge in the new server bag with the updated `REQUEST_URI`.
         */
        $request = request()->duplicate(
            server: $serverBag->all()
        );

        return $request;
    }

    protected function formatPath($path)
    {
        return '/' . ltrim($path, '/');
    }

    protected function getApplicablePersistentMiddleware($request)
    {
        $route = $this->getRouteFromRequest($request);

        if (! $route) return [];

        $middleware = app('router')->gatherRouteMiddleware($route);

        return $this->filterMiddlewareByPersistentMiddleware($middleware);
    }

    protected function getRouteFromRequest($request)
    {
        $route = app('router')->getRoutes()->match($request);

        $request->setRouteResolver(fn() => $route);

        return $route;
    }

    protected function filterMiddlewareByPersistentMiddleware($middleware)
    {
        $middleware = collect($middleware);

        $persistentMiddleware = collect(app(PersistentMiddleware::class)->getPersistentMiddleware());

        return $middleware
            ->filter(function ($value, $key) use ($persistentMiddleware) {
                return $persistentMiddleware->contains(function($iValue, $iKey) use ($value) {
                    // Some middlewares can be closures.
                    if (! is_string($value)) return false;

                    // Ensure any middleware arguments aren't included in the comparison
                    return Str::before($value, ':') == $iValue;
                });
            })
            ->values()
            ->all();
    }
}
