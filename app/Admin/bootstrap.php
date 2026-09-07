<?php

use Dcat\Admin\Admin;

Admin::css('/demo/demo.css');
Admin::js('/demo/demo.js');

// MultiRow translates its field labels; keep these in the UI's authorized scope.
app('translator')->addLines([
    'global.fields.title' => '标题',
    'global.fields.code' => '编码',
    'global.fields.status' => '启用状态',
    'global.fields.balance' => '演示余额',
    'global.fields.url' => '链接',
    'global.fields.description' => '描述',
], app()->getLocale());

// The package shell uses its first menu item as the initial iframe.
// Replace only the in-memory menu on that request; persisted menus remain shared.
if (request()->is(trim(config('admin.route.prefix'), '/').'/demo/tabs')) {
    admin_inject_section(Admin::SECTION['LEFT_SIDEBAR_MENU'], function () {
        return view('demo.tab-menu');
    });
}
