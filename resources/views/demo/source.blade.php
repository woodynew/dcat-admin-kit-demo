@php($sourceId = 'demo-source-'.($sourceKey ?? \Illuminate\Support\Str::random(10)))
<div class="demo-source-block">
    <div class="demo-source-toolbar"><span>{{ $sourceLabel ?? '正在运行的源码' }}</span><button type="button" class="demo-copy-source" data-copy-target="{{ $sourceId }}" data-testid="copy-source" aria-label="复制{{ $sourceLabel ?? '源码' }}">复制源码</button></div>
    <pre><code id="{{ $sourceId }}">{{ $source }}</code></pre>
    <span class="demo-copy-result" role="status" aria-live="polite"></span>
</div>
