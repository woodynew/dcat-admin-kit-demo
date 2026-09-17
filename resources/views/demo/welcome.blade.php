<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Dcat Admin Kit 中文交互演示，体验 14 个组件与 8 个全局特性。">
    <title>Dcat Admin Kit · 让后台交互更顺手</title>
    <link rel="stylesheet" href="{{ asset('demo/demo.css') }}">
</head>
<body class="demo-welcome">
    <header class="demo-public-nav">
        <a class="demo-brand" href="{{ url('/') }}"><span class="demo-brand-mark">K</span> Dcat Admin Kit <small>PLAYGROUND</small></a>
        <nav aria-label="项目链接">
            <a href="{{ \App\Support\DemoExamples::DOCS_URL }}" target="_blank" rel="noopener noreferrer">Kit 组件文档 ↗</a>
            <a href="{{ \App\Support\DemoExamples::DEMO_URL }}" target="_blank" rel="noopener noreferrer">GitHub ↗</a>
        </nav>
    </header>
    <main class="demo-public-main">
        <div class="demo-landing-copy">
            <p class="demo-eyebrow">为 Dcat Admin 构建 · 开源组件工具箱</p>
            <h1>让后台交互，<br><span>更顺手一点。</span></h1>
            <p class="demo-lead">从一列信息的呈现，到一次表单的提交。<br>在真实后台中体验组件，查看参数，复制正在运行的源码。</p>
            <div class="demo-landing-actions">
                <form method="POST" action="{{ route('demo.enter') }}" data-testid="demo-enter-form">
                    @csrf
                    <button class="demo-primary" type="submit" data-testid="demo-enter">进入交互演示 <span aria-hidden="true">→</span></button>
                </form>
                <a class="demo-text-link" href="{{ \App\Support\DemoExamples::DEMO_URL }}/blob/main/README.md" target="_blank" rel="noopener noreferrer">阅读使用指南 ↗</a>
            </div>
            @if ($errors->any())
                <div class="demo-notice" role="alert">{{ $errors->first() }}</div>
            @endif
            <div class="demo-shared-note">
                <span class="demo-dot"></span>
                <div><strong>这是一个所有访客共享的演示空间</strong>
                    <p>修改会实际保存并对其他访客可见，每小时重置。请使用演示内容。</p>
                    <p>最近重置：<time data-testid="last-reset-at">{{ $lastResetAt ?: '尚未初始化' }}</time></p>
                </div>
            </div>
        </div>
        <aside class="demo-landing-panel" aria-label="演示内容概览">
            <div class="demo-panel-caption"><span class="demo-dot"></span> 真实组件 · 即点即用 <span>01 / KIT</span></div>
            <div class="demo-showcase-row"><span class="demo-showcase-icon">↗</span><div><strong>信息呈现，恰到好处</strong><p>二维码复制 · 多行信息 · 文本展开</p></div><span>04</span></div>
            <div class="demo-showcase-row"><span class="demo-showcase-icon">⌘</span><div><strong>操作路径，少一步</strong><p>顶部提交 · 复制记录 · 弹窗表单</p></div><span>08</span></div>
            <div class="demo-showcase-row"><span class="demo-showcase-icon">▤</span><div><strong>数据结果，看得见</strong><p>汇总表格 · 请求形态 · 实际操作日志</p></div><span>02</span></div>
            <div class="demo-panel-bottom"><strong>14 <small>个组件</small></strong><strong>8 <small>个特性开关</small></strong></div>
            <p class="demo-panel-note">原生 Dcat 布局 / 中文说明 / 精确源码</p>
        </aside>
        <section class="demo-landing-links" aria-label="项目资源">
            <a href="{{ \App\Support\DemoExamples::REPOSITORY_URL }}" target="_blank" rel="noopener noreferrer"><strong>Dcat Admin Kit ↗</strong><span>通用扩展包 · {{ \App\Support\DemoExamples::kitVersion() }}</span></a>
            <a href="https://github.com/woodynew/dcat-admin" target="_blank" rel="noopener noreferrer"><strong>Dcat Laravel Admin ↗</strong><span>后台基础 · {{ \App\Support\DemoExamples::dcatVersion() }}</span></a>
            <a href="{{ \App\Support\DemoExamples::BASE_DOCS_URL }}" target="_blank" rel="noopener noreferrer"><strong>Dcat 基础文档 ↗</strong><span>Grid / Form / Show</span></a>
        </section>
    </main>
    <footer class="demo-public-footer">Dcat Admin Kit Playground <span>Laravel 12 · 本地静态资源 · 无外部上传</span></footer>
</body>
</html>
