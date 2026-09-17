@php
    $methodMap = ['grid_defaults' => 'registerGridDefaults', 'form_defaults' => 'registerFormDefaults', 'show_defaults' => 'registerShowDefaults', 'right_side_filter' => 'registerFilterDefaults', 'top_form_tools' => 'registerFormDefaults', 'back_to_top' => 'registerBackToTop', 'grid_assets' => 'registerGridDefaults', 'global_styles' => 'registerGlobalAssets'];
@endphp
<div class="demo-ui" data-testid="demo-preview" data-feature="{{ $feature }}" data-enabled="{{ $enabled ? 1 : 0 }}">
    @include('demo.nav')
    <section class="demo-page-intro"><p class="demo-eyebrow">FEATURE PREVIEW / {{ $feature }}</p><h2>{{ $features[$feature][0] }} <span class="demo-status-pill">{{ $enabled ? __('开启') : __('关闭') }}</span></h2><p>{{ $features[$feature][1] }}</p></section>
    <div class="demo-preview-controls">
        <div class="demo-toggle-links"><a class="{{ !$enabled ? 'is-active' : '' }}" href="{{ request()->url().'?'.http_build_query(['feature' => $feature, 'enabled' => 0]) }}" data-pjax="0" data-testid="preview-off">{{ __('关闭') }}</a><a class="{{ $enabled ? 'is-active' : '' }}" href="{{ request()->url().'?'.http_build_query(['feature' => $feature, 'enabled' => 1]) }}" data-pjax="0" data-testid="preview-on">{{ __('开启') }}</a></div>
        <nav aria-label="{{ __('预览组件') }}">
            @foreach(['preview' => __('表格'), 'preview-form' => __('表单'), 'preview-show' => __('详情')] as $path => $label)
                @if($path !== 'preview-show' || $recordId)<a href="{{ admin_url('demo/'.$path.($path === 'preview-show' ? '/'.$recordId : '')).'?'.http_build_query(['feature' => $feature, 'enabled' => (int) $enabled]) }}" data-pjax="0" data-testid="preview-{{ $path }}">{{ $label }}</a>@endif
            @endforeach
        </nav>
    </div>
    <div class="demo-live-example" data-testid="live-example">{!! $example !!}</div>
    @if($feature === 'grid_assets')
        <section class="demo-example-card"><h3>{{ __('真实横纵滚动布局') }}</h3>
            <p>{{ __('本例在开启和关闭状态都使用 Dcat 原生宽表布局，并固定 320px 高度。关闭时使用浏览器滚动条，开启后 Kit 在表格的 .table-main 容器创建 NiceScroll。横向可滚动查看右侧完整链接与描述，纵向可滚动查看全部记录；切换顶部亮/暗主题即可对照两条滚动条的暗色适配。') }}</p>
            @include('demo.source', ['source' => \App\Support\DemoExamples::source('wideGridLayout'), 'sourceKey' => 'wide-grid-layout', 'sourceLabel' => 'DemoExamples::wideGridLayout'])
        </section>
    @endif
    <section class="demo-example-card"><h3>{{ __('当前请求实际配置') }}</h3>
        <p><code>dcat-admin-kit.features.{{ $feature }}</code> = <strong data-testid="actual-feature-value">{{ config('dcat-admin-kit.features.'.$feature) ? 'true' : 'false' }}</strong>. {{ __('其余开关沿用站点配置，语言选择器始终可用。') }}</p>
        <p>{{ __('表单和记录操作会保存到共享数据；离开对照页后恢复普通演示页面。') }}</p>
    </section>
    <section class="demo-example-card"><h3>{{ __('已安装 Kit 的实际特性实现') }}</h3><p>{{ __('以下方法由当前安装的 Kit 源码直接提取，配置在所有 Provider boot 之前确定。') }}</p>
        @include('demo.source', ['source' => \App\Support\DemoExamples::source($methodMap[$feature], \Woodynew\DcatAdminKit\Bootstrapper::class), 'sourceKey' => 'feature-implementation', 'sourceLabel' => 'Woodynew\DcatAdminKit\Bootstrapper::'.$methodMap[$feature]])
        <details><summary>{{ __('查看 Demo 当前请求的配置接入') }}</summary>@include('demo.source', ['source' => \App\Support\DemoExamples::source('register', \App\Providers\AppServiceProvider::class), 'sourceKey' => 'preview-config', 'sourceLabel' => 'AppServiceProvider::register'])</details>
    </section>
    @if(in_array($feature, ['back_to_top', 'grid_assets']))
        <section class="demo-scroll-sample"><p class="demo-eyebrow">SCROLL TO EXPLORE</p><h3>{{ __('继续向下滚动') }}</h3><p>{{ __('观察右下角返回按钮或上方表格的滚动体验。') }}</p><span>↓</span></section>
    @endif
    <div class="demo-resource-links"><a href="{{ admin_url('demo/features') }}" data-pjax="0">{{ __('← 全部八项特性') }}</a><a href="{{ \App\Support\DemoExamples::DOCS_URL }}" target="_blank" rel="noopener noreferrer">{{ __('Kit 组件文档 ↗') }}</a><a href="{{ \App\Support\DemoExamples::DEMO_URL }}/blob/main/README.md" target="_blank" rel="noopener noreferrer">{{ __('Demo 使用指南 ↗') }}</a></div>
</div>
