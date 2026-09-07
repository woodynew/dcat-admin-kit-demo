<nav class="demo-section-nav" aria-label="演示导航" data-testid="demo-nav">
    @foreach (['overview' => '概览', 'columns' => '列显示器', 'actions' => '操作与工具', 'records' => '共享记录', 'widgets' => '组件', 'features' => '全局特性', 'tabs' => '标签页实验室'] as $path => $label)
        <a href="{{ admin_url('demo/'.$path).(request()->query('iframe') === '1' && $path !== 'tabs' ? '?iframe=1' : '') }}"
           @if(in_array($path, ['features', 'tabs'])) data-pjax="0" @endif
           @if($path === 'tabs') target="_top" @endif
           class="{{ request()->is('*/demo/'.$path, '*/demo/'.$path.'/*') ? 'is-active' : '' }}"
           data-testid="nav-{{ $path }}">{{ $label }}</a>
    @endforeach
</nav>
