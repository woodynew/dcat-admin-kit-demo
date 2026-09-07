<div class="demo-ui" data-testid="demo-features">
    @include('demo.nav')
    <section class="demo-page-intro"><p class="demo-eyebrow">GLOBAL FEATURES / 08 SWITCHES</p><h2>一个开关，一次清晰对照。</h2><p>选择「关闭」或「开启」，在新请求中查看真实组件变化。八项特性独立预览，不会改变其他访客的配置。</p></section>
    <div class="demo-feature-grid">
        @foreach($features as $key => [$title, $notes, $target])
            @php($path = $target === 'preview-show' && $recordId ? $target.'/'.$recordId : ($target === 'preview-show' ? 'preview-form' : $target))
            <article class="demo-feature-card" data-testid="feature-{{ $key }}"><span class="demo-card-kicker">{{ sprintf('%02d', $loop->iteration) }} / 默认关闭</span><h3>{{ $title }}</h3><code>{{ $key }}</code><p>{{ $notes }}</p>
                <div class="demo-toggle-links" role="group" aria-label="{{ $title }}对照">
                    <a href="{{ admin_url('demo/'.$path).'?'.http_build_query(['feature' => $key, 'enabled' => 0]) }}" data-pjax="0" data-testid="feature-{{ $key }}-off">关闭 · 原生</a>
                    <a href="{{ admin_url('demo/'.$path).'?'.http_build_query(['feature' => $key, 'enabled' => 1]) }}" data-pjax="0" data-testid="feature-{{ $key }}-on">开启 · Kit →</a>
                </div>
                @if($key === 'show_defaults' && !$recordId)<small>当前没有记录，请先创建一条，再体验详情对照。</small>@endif
            </article>
        @endforeach
    </div>
    <div class="demo-notice"><strong>每项开关都会展示相应组件</strong><p>表格开关观察按钮、筛选与滚动；表单开关观察顶部工具及底部选项；详情开关观察编辑和删除入口。预览表单使用真实保存流程。</p></div>
</div>
