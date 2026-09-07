<?php

use App\Http\Middleware\DemoAccess;
use Dcat\Admin\Http\Controllers\AuthController;

$config = require base_path('vendor/woodynew/dcat-laravel-admin/config/admin.php');

return array_replace_recursive($config, [
    'name' => 'Dcat Admin Kit Demo',
    'title' => '组件展示台',
    'logo' => '<b>Dcat Kit</b> · 组件展示台',
    'logo-mini' => '<b>Kit</b>',
    'route' => [
        'prefix' => 'admin',
        'middleware' => ['web', DemoAccess::class, 'admin'],
    ],
    'auth' => ['controller' => AuthController::class],
    'helpers' => ['enable' => false],
]);
