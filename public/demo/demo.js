(function () {
    if (window.__demoUiInstalled) return;
    window.__demoUiInstalled = true;

    function inRealTabFrame() {
        try { return window !== window.parent && !!window.parent.iframeTabParent; }
        catch (_) { return false; }
    }

    function withFrameContext(value) {
        if (!value || value[0] === '#' || /^javascript:/i.test(value) || !inRealTabFrame()) return value;
        var url = new URL(value, location.href);
        if (url.origin !== location.origin || !/\/demo\//.test(url.pathname)
            || /\/demo\/tabs\/?$/.test(url.pathname)) return value;
        url.searchParams.set('iframe', '1');
        return url.href;
    }

    function preserveFrameContext() {
        if (!inRealTabFrame()) return;
        document.querySelectorAll('a[href], form[action], [data-redirect]').forEach(function (element) {
            ['href', 'action', 'data-redirect'].forEach(function (attribute) {
                if (element.hasAttribute(attribute)) {
                    element.setAttribute(attribute, withFrameContext(element.getAttribute(attribute)));
                }
            });
        });
        document.querySelectorAll('input[name="_previous_"], input[name="_current_"]').forEach(function (input) {
            input.value = withFrameContext(input.value);
        });
    }

    preserveFrameContext();
    document.addEventListener('DOMContentLoaded', preserveFrameContext);
    if (window.jQuery) window.jQuery(document).on('pjax:complete.demoUi', preserveFrameContext);
    document.addEventListener('submit', preserveFrameContext, true);

    function fallbackCopy(text) {
        var input = document.createElement('textarea');
        input.value = text;
        input.style.position = 'fixed';
        input.style.opacity = '0';
        document.body.appendChild(input);
        input.select();
        var copied = document.execCommand('copy');
        input.remove();
        if (!copied) throw new Error('copy failed');
    }

    document.addEventListener('click', async function (event) {
        var button = event.target.closest('[data-copy-target]');
        if (!button) return;
        var source = document.getElementById(button.dataset.copyTarget);
        var result = button.closest('.demo-source-block').querySelector('.demo-copy-result');
        if (!source) return;
        try {
            if (navigator.clipboard && window.isSecureContext) {
                try { await navigator.clipboard.writeText(source.textContent); }
                catch (_) { fallbackCopy(source.textContent); }
            } else { fallbackCopy(source.textContent); }
            result.textContent = '源码已复制';
        } catch (_) {
            result.textContent = '自动复制未完成，请选中源码手动复制';
        }
    });

    // Features need a fresh document so global CSS and hooks cannot leak via PJAX.
    document.addEventListener('click', function (event) {
        var link = event.target.closest('a[href]');
        if (inRealTabFrame()) {
            // Native form buttons may send AJAX before a submit event is dispatched.
            preserveFrameContext();
        }
        if (!link || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || event.button !== 0) return;
        var href = link.getAttribute('href') || '';
        // Native iframe/Bootstrap tabs use hash anchors; let the package own them.
        if (!href || href[0] === '#' || /^javascript:/i.test(href)) return;
        var url = new URL(link.href, location.href);
        if (url.origin !== location.origin) return;
        var preview = /\/demo\/(features|preview(?:-form|-show)?)(?:\/|$)/;
        var shell = /\/demo\/tabs\/?$/;
        if (preview.test(url.pathname) || preview.test(location.pathname) || shell.test(url.pathname)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            if (shell.test(url.pathname) && window.top !== window) window.top.location.href = url.href;
            else location.href = url.href;
        }
    }, true);
})();
