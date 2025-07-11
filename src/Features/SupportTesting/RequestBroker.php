<?php

namespace Livewire\Features\SupportTesting;

use GSManager\Foundation\Testing\Concerns\InteractsWithExceptionHandling;
use GSManager\Foundation\Testing\Concerns\MakesHttpRequests;
use Symfony\Component\HttpKernel\Exception\HttpException;
use GSManager\Auth\Access\AuthorizationException;
use GSManager\Contracts\Debug\ExceptionHandler;

class RequestBroker
{
    use MakesHttpRequests, InteractsWithExceptionHandling;

    protected $app;

    function __construct($app)
    {
        $this->app = $app;
    }

    function temporarilyDisableExceptionHandlingAndMiddleware($callback)
    {
        $cachedHandler = app(ExceptionHandler::class);

        $cachedShouldSkipMiddleware = $this->app->shouldSkipMiddleware();

        $this->withoutExceptionHandling([HttpException::class, AuthorizationException::class])->withoutMiddleware();

        $result = $callback($this);

        $this->app->instance(ExceptionHandler::class, $cachedHandler);

        if (! $cachedShouldSkipMiddleware) {
            unset($this->app['middleware.disable']);
        }

        return $result;
    }

    function withoutHandling($except = [])
    {
        return $this->withoutExceptionHandling($except);
    }

    function addHeaders(array $headers)
    {
        $this->serverVariables = $this->transformHeadersToServerVars($headers);

        return $this;
    }
}
