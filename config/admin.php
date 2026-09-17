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
    'layout' => [
        // 顶栏的亮/暗主题切换按钮，Dcat 核心负责切换与 localStorage 记忆。
        'dark_mode_switch' => true,
    ],
    'auth' => ['controller' => AuthController::class],
    'helpers' => ['enable' => false],
]);
