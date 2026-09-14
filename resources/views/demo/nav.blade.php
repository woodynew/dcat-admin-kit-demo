<nav class="demo-section-nav" aria-label="{{ __('演示导航') }}" data-testid="demo-nav">
    @foreach (['overview' => __('概览'), 'columns' => __('列显示器'), 'actions' => __('操作与工具'), 'records' => __('共享记录'), 'widgets' => __('组件'), 'features' => __('全局特性'), 'tabs' => __('标签页实验室')] as $path => $label)
        <a href="{{ admin_url('demo/'.$path).(request()->query('iframe') === '1' && $path !== 'tabs' ? '?iframe=1' : '') }}"
           @if(in_array($path, ['features', 'tabs'])) data-pjax="0" @endif
           @if($path === 'tabs') target="_top" @endif
           class="{{ request()->is('*/demo/'.$path, '*/demo/'.$path.'/*') ? 'is-active' : '' }}"
           data-testid="nav-{{ $path }}">{{ $label }}</a>
    @endforeach
</nav>
