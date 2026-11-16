<?php

return ['features' => [
    'grid_defaults' => false,
    'form_defaults' => false,
    'show_defaults' => false,
    'right_side_filter' => false,
    'top_form_tools' => false,
    'back_to_top' => false,
    // 与 locale_switcher 一样，Demo 站点整站启用：表格样式由 Kit 统一提供，不在应用内另写补丁。
    'grid_assets' => true,
    // 覆盖与业务项目相同的 Kit 全局布局和 iframe 标签栏组合。
    'global_styles' => true,
    'locale_switcher' => true,
]];
