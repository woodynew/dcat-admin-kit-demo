@php($sourceId = 'demo-source-'.($sourceKey ?? \Illuminate\Support\Str::random(10)))
<div class="demo-source-block">
    <div class="demo-source-toolbar"><span>{{ $sourceLabel ?? __('正在运行的源码') }}</span><button type="button" class="demo-copy-source" data-copy-target="{{ $sourceId }}" data-testid="copy-source" aria-label="{{ __('复制源码') }}: {{ $sourceLabel ?? __('源码') }}"
        data-copy-success="{{ __('源码已复制') }}" data-copy-failed="{{ __('自动复制未完成，请选中源码手动复制') }}">{{ __('复制源码') }}</button></div>
    <pre><code id="{{ $sourceId }}">{{ $source }}</code></pre>
    <span class="demo-copy-result" role="status" aria-live="polite"></span>
</div>
