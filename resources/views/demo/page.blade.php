<div class="demo-ui" data-testid="demo-{{ $section }}">
    @include('demo.nav')
    <section class="demo-page-intro"><p class="demo-eyebrow">LIVE EXAMPLE / {{ strtoupper($section) }}</p><h2>{{ $title }}</h2><p>{{ $description }}</p></section>
    <div class="demo-live-example" data-testid="live-example">{!! $example !!}</div>
    @if($withLogs)
        <section class="demo-log-section" data-testid="demo-logs"><div class="demo-section-heading"><h3>最近操作日志</h3><a href="{{ request()->fullUrl() }}" data-pjax="0">刷新结果 ↻</a></div>{!! \App\Support\DemoExamples::logs()->render() !!}</section>
    @endif
    @if($section === 'actions')
        <div class="demo-notice"><strong>想体验标签页？</strong><p>本页的「标签详情」支持普通跳转；进入 <a href="{{ admin_url('demo/tabs') }}" data-pjax="0" target="_top" data-testid="open-tabs">标签页实验室 ↗</a> 后，在父容器中打开并复用同一条记录的详情。</p></div>
    @endif
    @include('demo.examples', ['section' => $section])
</div>
