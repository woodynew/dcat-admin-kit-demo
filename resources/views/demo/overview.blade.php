<div class="demo-ui" data-testid="demo-overview">
    @include('demo.nav')
    <section class="demo-hero">
        <div><p class="demo-eyebrow">DCAT ADMIN KIT / PLAYGROUND</p><h2>{{ __('小组件，顺手的后台。') }}</h2>
            <p>{{ __('体验真实交互，再把正在运行的源码带回你的项目。') }}</p>
            <a class="demo-primary" href="{{ admin_url('demo/columns') }}">{{ __('从列显示器开始 →') }}</a>
        </div>
        <div class="demo-hero-stats"><div><strong>14</strong><span>{{ __('通用组件') }}</span></div><div><strong>08</strong><span>{{ __('全局特性') }}</span></div></div>
    </section>
    <div class="demo-metrics">
        <div><span>{{ __('共享记录') }}</span><strong data-testid="record-count">{{ $recordCount }} <small>/ 200</small></strong></div>
        <div><span>{{ __('操作日志') }}</span><strong>{{ $logCount }} <small>/ 500</small></strong></div>
        <div class="demo-reset-metric"><span>{{ __('最近重置 · 每小时自动重置') }}</span><strong data-testid="last-reset-at">{{ $lastResetAt ?: __('尚未初始化') }}</strong></div>
    </div>
    <div class="demo-section-heading"><h3>{{ __('按场景探索') }}</h3><span>{{ __('每个示例都包含参数、说明与实际源码') }}</span></div>
    <div class="demo-catalog-grid">
        @foreach ([['columns', '01', __('列显示器'), __('让一列信息更好读'), __('二维码与复制、多字段合并、长文本弹窗和尾部展开。'), __('4 个组件')], ['actions', '02', __('操作与工具'), __('把常用操作放在手边'), __('文字行操作、顶部通知、余额弹窗与标签页跳转。'), __('5 个组件')], ['records', '03', __('共享记录'), __('完成一次真实的提交'), __('新建、编辑与复制；观察顶部提交和请求形态日志。'), __('4 个组件')], ['widgets', '04', __('组件与汇总'), __('把操作结果摆出来'), __('多实例 PostTable、真实记录及最近操作日志。'), __('1 个组件')], ['features', '05', __('全局特性'), __('逐个开关，逐项对照'), __('每次请求只启用所选特性，比较 Grid、Form 与 Show。'), __('8 个开关')], ['tabs', '06', __('标签页实验室'), __('在真正的 iframe 中体验'), __('打开、复用、关闭标签，体验顶部返回与详情跳转。'), 'iframe 1.2.1']] as [$path, $number, $title, $subtitle, $description, $count])
            <a class="demo-catalog-card" href="{{ admin_url('demo/'.$path) }}" @if(in_array($path, ['features', 'tabs'])) data-pjax="0" @endif>
                <span class="demo-card-kicker">{{ $number }} / {{ $count }} <span>↗</span></span><h3>{{ $title }}</h3><strong>{{ $subtitle }}</strong><p>{{ $description }}</p>
            </a>
        @endforeach
    </div>
    <div class="demo-notice"><strong>{{ __('共享空间 · 真实保存') }}</strong><p>{{ __('你的修改对所有访客可见，数据每小时重置。复制记录会重新生成编码，演示余额不代表真实资金。全局特性预览只影响当前请求。') }}</p></div>
    <div class="demo-resource-links"><a href="{{ \App\Support\DemoExamples::REPOSITORY_URL }}" target="_blank" rel="noopener noreferrer">Kit GitHub ↗</a><a href="{{ \App\Support\DemoExamples::DOCS_URL }}" target="_blank" rel="noopener noreferrer">{{ __('Kit 组件文档 ↗') }}</a><a href="{{ \App\Support\DemoExamples::DEMO_URL }}/blob/main/README.md" target="_blank" rel="noopener noreferrer">{{ __('演示使用指南 ↗') }}</a><span>Dcat 2.2.4 · Kit 0.1.0 · Laravel {{ app()->version() }}</span></div>
</div>
