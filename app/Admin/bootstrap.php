<?php

use Dcat\Admin\Admin;

config(['admin.title' => __('组件展示台'), 'admin.logo' => '<b>Dcat Kit</b> · '.__('展示台')]);
// Translate menu labels at render time without modifying shared menu records.
$menuTranslations = [];
foreach (['演示概览', '列显示器', '操作与工具', '共享记录', '组件展示', '全局特性', '多标签页'] as $label) {
    $menuTranslations['menu.titles.'.$label] = __($label);
}
app('translator')->addLines($menuTranslations, app()->getLocale());

// Dcat's package version is unchanged during local edits; version Demo assets by content.
Admin::css('/demo/demo.css?rev='.substr(hash_file('sha256', public_path('demo/demo.css')), 0, 12));
Admin::js('/demo/demo.js?rev='.substr(hash_file('sha256', public_path('demo/demo.js')), 0, 12));

// MultiRow translates its field labels; keep these in the UI's authorized scope.
app('translator')->addLines([
    'global.fields.title' => __('标题'),
    'global.fields.code' => __('编码'),
    'global.fields.status' => __('启用状态'),
    'global.fields.balance' => __('演示余额'),
    'global.fields.url' => __('链接'),
    'global.fields.description' => __('描述'),
], app()->getLocale());

// The package shell uses its first menu item as the initial iframe.
// Replace only the in-memory menu on that request; persisted menus remain shared.
if (request()->is(trim(config('admin.route.prefix'), '/').'/demo/tabs')) {
    admin_inject_section(Admin::SECTION['LEFT_SIDEBAR_MENU'], function () {
        return view('demo.tab-menu');
    });
}
