@foreach(['actions' => [__('标签页交互'), 'feather icon-layers'], 'records' => [__('共享记录'), 'feather icon-database'], 'columns' => [__('列显示器'), 'feather icon-grid'], 'widgets' => [__('组件与日志'), 'feather icon-layout']] as $path => [$label, $icon])
    <li class="nav-item"><a class="nav-link" href="{{ admin_url('demo/'.$path).'?iframe=1' }}" data-testid="tabs-menu-{{ $path }}"><i class="fa fa-fw {{ $icon }}"></i><p>{{ $label }}</p></a></li>
@endforeach
