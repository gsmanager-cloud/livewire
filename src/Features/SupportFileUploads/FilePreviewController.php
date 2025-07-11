<?php

namespace Livewire\Features\SupportFileUploads;

use GSManager\Routing\Controllers\HasMiddleware;
use GSManager\Routing\Controllers\Middleware;
use Livewire\Drawer\Utils;

class FilePreviewController implements HasMiddleware
{
    public static array $middleware = ['web'];

    public static function middleware()
    {
        return array_map(fn ($middleware) => new Middleware($middleware), static::$middleware);
    }

    public function handle($filename)
    {
        abort_unless(request()->hasValidSignature(), 401);

        return Utils::pretendPreviewResponseIsPreviewFile($filename);
    }
}
