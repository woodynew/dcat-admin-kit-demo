<section class="demo-example-docs" data-testid="component-docs">
    <div class="demo-section-heading"><h3>把示例带回项目</h3><span>通过 Reflection 提取当前执行方法，源码随实现一起更新</span></div>
    @foreach(\App\Support\DemoExamples::components() as $name => [$area, $title, $method, $notes, $doc])
        @if($section === $area)
            <article class="demo-example-card" id="component-{{ $name }}" data-testid="component-{{ $name }}">
                <div class="demo-section-heading"><h3>{{ $title }} <code>{{ $name }}</code></h3><a href="{{ \App\Support\DemoExamples::DOCS_URL }}" target="_blank" rel="noopener noreferrer">Kit 组件文档 ↗</a></div>
                <p>{{ $notes }}</p>
                @include('demo.source', ['source' => \App\Support\DemoExamples::source($method), 'sourceKey' => $name, 'sourceLabel' => 'App\Support\DemoExamples::'.$method])
                @if($name === 'Copy')
                    <details><summary>查看同一创建流程使用的预填逻辑</summary>@include('demo.source', ['source' => \App\Support\DemoExamples::source('copyDefaults'), 'sourceKey' => 'copy-defaults', 'sourceLabel' => '仅复制允许的业务字段'])</details>
                @elseif($name === 'GridFormTool')
                    <details><summary>查看 BulkNoticeForm 完整源码与真实提交</summary>@include('demo.source', ['source' => \App\Support\DemoExamples::classSource(\App\Admin\Forms\BulkNoticeForm::class), 'sourceKey' => 'bulk-form', 'sourceLabel' => 'App\Admin\Forms\BulkNoticeForm'])</details>
                @elseif($name === 'GridModalRowAction')
                    <details><summary>查看 BalanceForm 完整源码与参数验证</summary>@include('demo.source', ['source' => \App\Support\DemoExamples::classSource(\App\Admin\Forms\BalanceForm::class), 'sourceKey' => 'balance-form', 'sourceLabel' => 'App\Admin\Forms\BalanceForm'])</details>
                @endif
            </article>
        @endif
    @endforeach
    <details class="demo-example-card"><summary>命名空间与 use 导入 · 当前示例的实际声明</summary>
        <p>以上为 DemoExamples 类中被页面调用的方法。集成时使用下列实际导入，或从 GitHub 查看完整类文件。</p>
        @include('demo.source', ['source' => \App\Support\DemoExamples::imports(), 'sourceKey' => 'imports', 'sourceLabel' => 'DemoExamples.php 文件头'])
    </details>
    @if($section === 'records')
        <details class="demo-example-card"><summary>表单字段定义与写入 Repository</summary>
            @include('demo.source', ['source' => \App\Support\DemoExamples::source('recordForm'), 'sourceKey' => 'record-form', 'sourceLabel' => '共享的 Dcat 表单定义'])
            @include('demo.source', ['source' => \App\Support\DemoExamples::classSource(\App\Admin\Repositories\DemoRecordRepository::class), 'sourceKey' => 'record-repository', 'sourceLabel' => '保存、更新、删除统一调用 DemoData'])
        </details>
    @endif
    <div class="demo-resource-links"><a href="{{ \App\Support\DemoExamples::DEMO_URL }}/blob/main/app/Support/DemoExamples.php" target="_blank" rel="noopener noreferrer">完整示例源码 ↗</a><a href="{{ \App\Support\DemoExamples::DEMO_URL }}/blob/main/README.md" target="_blank" rel="noopener noreferrer">Demo 使用指南 ↗</a><a href="{{ \App\Support\DemoExamples::BASE_DOCS_URL }}" target="_blank" rel="noopener noreferrer">Dcat 基础文档 ↗</a></div>
</section>
