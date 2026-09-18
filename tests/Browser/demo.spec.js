import { test as base, expect } from '@playwright/test';
import { createHash } from 'node:crypto';
import { readFileSync } from 'node:fs';

// Catch uncaught JS errors (including child frames) and failed app/asset responses.
// Browser console chatter such as favicon requests is not a page exception.
const test = base.extend({
    page: async ({ page, baseURL }, use) => {
        const failures = [];
        page.on('pageerror', error => failures.push(error.stack ?? error.message));
        page.on('response', response => {
            const url = new URL(response.url());
            if (url.origin === new URL(baseURL).origin
                && url.pathname !== '/favicon.ico' && response.status() >= 400) {
                failures.push(`${response.status()} ${response.request().method()} ${url.pathname}`);
            }
        });
        await use(page);
        expect(failures, '页面脚本和站内资源不应报错').toEqual([]);
    },
});

async function enterDemo(page) {
    await page.goto('/');
    const form = page.locator('form[action$="/demo/enter"]');
    await expect(form.locator('input[name="_token"]')).toHaveValue(/\S+/);
    const entry = page.waitForRequest(request => new URL(request.url()).pathname === '/demo/enter');
    await form.locator('button[type="submit"]').click();
    const request = await entry;
    expect(request.method()).toBe('POST');
    expect(new URLSearchParams(request.postData()).get('_token')).toBeTruthy();
    await expect(page).toHaveURL(/\/admin\/demo\/overview$/);
    await expect(page.getByTestId('demo-overview')).toBeVisible();
}

async function saveRecord(page, path) {
    const saved = page.waitForResponse(response =>
        new URL(response.url()).pathname === path && response.request().method() === 'POST');
    // This anchor is Kit TopSubmit, which forwards to Dcat's native form submit.
    await page.getByRole('link', { name: /提交$/ }).click();
    const response = await saved;
    expect(response.ok(), await response.text()).toBeTruthy();
    expect((await response.json()).status).toBe(true);
    // 完整锚定：保存后的跳转一旦被二次拼上 admin 前缀（/admin/admin/...），这里必须失败。
    await expect(page).toHaveURL(/^https?:\/\/[^/]+\/admin\/demo\/records(?:\/\d+\/edit)?(?:\?.*)?$/);
    // Dcat can return to the source edit page after copying; verify in a fresh list.
    await page.goto('/admin/demo/records');
}

function recordRow(page, code) {
    return page.locator('#grid-table tbody tr').filter({ has: page.getByRole('cell', { name: code, exact: true }) });
}

// 展示给访客的日期一律是 yyyy-mm-dd h:i:s，不接受 Carbon 默认的 ISO8601。
const readableDate = /^\s*\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\s*$/;

test('首页使用 CSRF POST 一键进入受限演示', async ({ page }) => {
    await enterDemo(page);
    await expect(page.locator('body')).toContainText('演示访客');
});

test('列别名实际复制、生成二维码、展开和弹窗，并在 PJAX 返回后继续工作', async ({ page, context }) => {
    await context.grantPermissions(['clipboard-read', 'clipboard-write']);
    await enterDemo(page);
    await page.goto('/admin/demo/columns');

    async function exerciseColumns() {
        const row = page.locator('#grid-table tbody tr').filter({ hasText: 'KIT-CHINESE' });
        const copy = row.locator('.dcat-admin-kit-copyable');
        const fullURL = await copy.getAttribute('data-content');
        await copy.click();
        await expect.poll(() => page.evaluate(() => navigator.clipboard.readText())).toBe(fullURL);
        expect(fullURL).toBe('https://github.com/woodynew/dcat-admin-kit');

        await row.locator('.dcat-admin-kit-qrcode').click();
        const qr = page.locator('.popover.show img');
        await expect(qr).toBeVisible();
        await expect(qr).toHaveAttribute('src', /^data:image\//);
        await expect.poll(() => qr.evaluate(image => image.naturalWidth)).toBeGreaterThan(0);
        await page.locator('section.content-header').click();

        const limit = row.locator('[id^="dcat-admin-kit-limit-"]');
        const shortText = await limit.locator('.limit-text:visible .text').innerText();
        await limit.locator('.limit-more:visible').click();
        await expect(limit.locator('.limit-text:visible .text')).toHaveText('这是一段可以展开和收起的中文说明。尾部截取会保留最后的内容，点击箭头即可阅读完整说明。');
        await limit.locator('.limit-more:visible').click();
        await expect(limit.locator('.limit-text:visible .text')).toHaveText(shortText);

        await row.locator('[id^="dcat-admin-kit-alert-"]').click();
        await expect(page.locator('.layui-layer-content')).toHaveText('欢迎体验 Dcat Admin Kit。这里展示的是固定纯文本，点击即可查看完整说明。');
        await page.locator('.layui-layer-btn0').click();
        await expect(page.locator('.layui-layer-content')).toHaveCount(0);
    }

    await exerciseColumns();
    const hostile = page.locator('#grid-table tbody tr').filter({ hasText: '特殊字符应该按文本显示' });
    await expect(hostile).toContainText('<img src=x onerror=alert(1)>');
    await expect(hostile.locator('script, img')).toHaveCount(0);
    const empty = page.locator('#grid-table tbody tr').filter({ hasText: 'KIT-EMPTY' });
    await expect(empty.locator('.dcat-admin-kit-copyable, .dcat-admin-kit-qrcode, .limit-more')).toHaveCount(0);

    // A document marker survives PJAX but is lost on a full navigation.
    await page.evaluate(() => { window.__demoPjaxMarker = 'columns'; });
    for (const section of ['overview', 'columns']) {
        const response = page.waitForResponse(response =>
            new URL(response.url()).pathname === `/admin/demo/${section}`
            && response.request().headers()['x-pjax'] === 'true');
        await page.locator(`.main-sidebar a[href$="/demo/${section}"]`).click();
        expect((await response).ok()).toBeTruthy();
        await expect(page).toHaveURL(new RegExp(`/admin/demo/${section}$`));
        expect(await page.evaluate(() => window.__demoPjaxMarker)).toBe('columns');
    }
    await exerciseColumns();
});

test('共享记录通过顶部提交创建、编辑，并从真实复制按钮创建独立记录', async ({ page }) => {
    // 「最近重置」在 demo_states 里存的是 ISO8601 字符串，展示时要转成可读格式。
    await page.goto('/');
    await expect(page.getByTestId('last-reset-at')).toHaveText(readableDate);
    await enterDemo(page);
    await expect(page.getByTestId('last-reset-at')).toHaveText(readableDate);
    await page.goto('/admin/demo/records');
    // 列表的更新时间列与共享日志的时间列。
    await expect(page.locator('#grid-table tbody tr').first().locator('td').nth(6)).toHaveText(readableDate);
    await expect(page.getByTestId('demo-logs').getByRole('row').nth(1).getByRole('cell').first()).toHaveText(readableDate);
    await page.getByRole('link', { name: /创建一条记录$/ }).click();
    await expect(page).toHaveURL(/\/admin\/demo\/records\/create$/);
    const code = `PW-${Date.now()}`;
    const title = `浏览器创建 ${code}`;
    await page.locator('input[name="title"]').fill(title);
    await page.locator('input[name="code"]').fill(code);
    await page.locator('input[name="url"]').fill('https://example.com/playwright');
    await page.locator('textarea[name="description"]').fill('中文、引号 "Hello" 与 Emoji 🌏');
    await saveRecord(page, '/admin/demo/records');
    await expect(recordRow(page, code)).toContainText(title);
    const editLink = recordRow(page, code).getByRole('link', { name: '编辑', exact: true });
    const editPath = new URL(await editLink.getAttribute('href'), page.url()).pathname;
    await editLink.click();
    await expect(page.locator('input[name="title"]')).toHaveValue(title);
    await page.locator('input[name="title"]').fill(`${title} 已编辑`);
    await saveRecord(page, editPath.replace(/\/edit$/, ''));
    await expect(recordRow(page, code)).toContainText(`${title} 已编辑`);
    await recordRow(page, code).getByRole('link', { name: '编辑', exact: true }).click();
    await page.getByRole('link', { name: /复制$/ }).click();
    await expect(page).toHaveURL(/\/records\/create\?copy=\d+$/);
    await expect(page.locator('input[name="title"]')).toHaveValue(`${title} 已编辑`);
    await expect(page.locator('input[name="url"]')).toHaveValue('https://example.com/playwright');
    await expect(page.locator('textarea[name="description"]')).toHaveValue('中文、引号 "Hello" 与 Emoji 🌏');
    const copiedCode = await page.locator('input[name="code"]').inputValue();
    expect(copiedCode).not.toBe(code);
    expect(copiedCode).toMatch(/^DEMO-/);
    await page.locator('input[name="title"]').fill(`${title} 副本`);
    await saveRecord(page, '/admin/demo/records');
    await expect(recordRow(page, copiedCode)).toContainText(`${title} 副本`);
    await expect(recordRow(page, copiedCode).getByRole('cell', { name: '0.00', exact: true })).toBeVisible();
    await expect(recordRow(page, code)).toContainText(`${title} 已编辑`);
    await page.reload();
    await expect(recordRow(page, copiedCode)).toContainText(`${title} 副本`);

    const status = recordRow(page, code).locator('.grid-column-switch');
    const wasEnabled = await status.isChecked();
    const switched = page.waitForResponse(response =>
        new URL(response.url()).pathname === editPath.replace(/\/edit$/, '')
        && response.request().method() === 'POST');
    await recordRow(page, code).locator('.switchery').click();
    expect((await switched).ok()).toBeTruthy();
    await expect(recordRow(page, code).locator('.grid-column-switch')).toBeChecked({ checked: !wasEnabled });
    await page.reload();
    await expect(recordRow(page, code).locator('.grid-column-switch')).toBeChecked({ checked: !wasEnabled });
    const recordId = editPath.match(/\/(\d+)\/edit$/)[1];
    const shapeLog = page.getByTestId('demo-logs').getByRole('row').filter({
        has: page.getByRole('cell', { name: recordId, exact: true }),
    });
    await expect(shapeLog).toContainText(['AdminFormUtil::isCreatingEditing = false · 局部状态更新']);

    await recordRow(page, code).getByRole('link', { name: /编辑$/ }).click();
    await expect(page).toHaveURL(new RegExp(`${editPath}$`));
    await page.getByTestId('live-example').getByRole('link', { name: /返回$/ }).click();
    await expect(page).toHaveURL(/\/admin\/demo\/records$/);
    await expect(recordRow(page, code)).toBeVisible();

    // 详情页的创建/更新时间也要落在同一格式上（Show 字段会被绑定到模型作用域）。
    await recordRow(page, code).getByRole('link', { name: /显示/ }).click();
    await expect(page).toHaveURL(new RegExp(`${editPath.replace(/\/edit$/, '')}$`));
    await expect(page.locator('.show-field', { hasText: '创建时间' }).locator('.box-body')).toHaveText(readableDate);
    await expect(page.locator('.show-field', { hasText: '更新时间' }).locator('.box-body')).toHaveText(readableDate);
});

test('表单顶部按钮保持原生配色与可读对比度', async ({ page }) => {
    const cssRevision = createHash('sha256').update(readFileSync(new URL('../../public/demo/demo.css', import.meta.url))).digest('hex').slice(0, 12);
    await enterDemo(page);
    await page.goto('/admin/demo/records');
    const editPath = await page.locator('#grid-table tbody tr').first()
        .getByRole('link', { name: /编辑/ }).getAttribute('href');

    for (const path of ['/admin/demo/records/create', editPath]) {
        await page.goto(path);
        const stylesheet = page.locator('link[rel="stylesheet"][href*="/demo/demo.css"]');
        await expect(stylesheet).toHaveCount(1);
        expect(new URL(await stylesheet.getAttribute('href'), page.url()).searchParams.get('rev')).toBe(cssRevision);
        const example = page.getByTestId('live-example');
        for (const label of ['提交', '返回', '列表', ...(path === editPath ? ['复制'] : [])]) {
            await expect(example.getByRole('link', { name: new RegExp(`${label}$`) })).toBeVisible();
        }
        const nativeColor = await example.locator('button.submit').first()
            .evaluate(button => getComputedStyle(button).color);
        const buttons = example.locator('a.btn-primary');
        expect(await buttons.count()).toBeGreaterThanOrEqual(3);
        for (const button of await buttons.all()) {
            for (const state of ['normal', 'hover', 'focus']) {
                await page.locator('h1').hover();
                if (state === 'hover') await button.hover();
                if (state === 'focus') await button.focus();
                await expect(button).toHaveCSS('color', nativeColor);
                await expect.poll(() => button.evaluate(element => {
                    const style = getComputedStyle(element);
                    const luminance = color => color.match(/[\d.]+/g).slice(0, 3).map(value => {
                        value = Number(value) / 255;
                        return value <= 0.04045 ? value / 12.92 : Math.pow((value + 0.055) / 1.055, 2.4);
                    }).reduce((sum, value, index) => sum + value * [0.2126, 0.7152, 0.0722][index], 0);
                    const values = [luminance(style.color), luminance(style.backgroundColor)].sort((a, b) => b - a);
                    return (values[0] + 0.05) / (values[1] + 0.05);
                })).toBeGreaterThanOrEqual(4.5);
            }
        }
    }
});

test('工具栏通知和行内余额弹窗真实写入并在刷新后保留', async ({ page }) => {
    await enterDemo(page);
    await page.goto('/admin/demo/actions');
    const notice = `浏览器通知 ${Date.now()}`;
    await page.getByRole('button', { name: '发布演示通知', exact: true }).click();
    const modal = page.locator('.modal.show');
    await modal.locator('textarea[name="content"]').fill(notice);
    const notified = page.waitForResponse(response => response.url().includes('/dcat-api/form') && response.request().method() === 'POST');
    await modal.locator('button[type="submit"]').click();
    expect((await notified).ok()).toBeTruthy();
    await expect(modal).toHaveCount(0);
    await page.reload();
    await expect(page.getByRole('cell', { name: notice, exact: true })).toBeVisible();

    const row = page.locator('#grid-table tbody tr').first();
    const cells = row.locator('td');
    const code = await cells.nth(3).innerText();
    const before = Number(await cells.nth(5).innerText());
    expect(Number.isFinite(before)).toBeTruthy();
    await row.getByRole('link', { name: '增加余额', exact: true }).click();
    await modal.locator('input[name="amount"]').fill('1.23');
    const balanced = page.waitForResponse(response => response.url().includes('/dcat-api/form') && response.request().method() === 'POST');
    await modal.locator('button[type="submit"]').click();
    expect((await balanced).ok()).toBeTruthy();
    await expect(modal).toHaveCount(0);
    await page.reload();
    await expect(recordRow(page, code).locator('td').nth(5)).toHaveText((before + 1.23).toFixed(2));
    await expect(page.getByRole('cell', { name: '增加演示余额 1.23', exact: true }).first()).toBeVisible();

    const fallback = recordRow(page, code).getByRole('link', { name: '标签详情', exact: true });
    const target = await fallback.getAttribute('data-to-url');
    await fallback.click();
    await expect(page).toHaveURL(target);
    await expect(page.locator('section.content-header')).toContainText('记录详情');
    await expect(page.locator('iframe')).toHaveCount(0);
});

test('真实 iframe 容器打开、复用并关闭记录标签', async ({ page }) => {
    await enterDemo(page);
    await page.goto('/admin/demo/tabs');
    const frames = page.locator('#iframe-tabContent iframe');
    await expect(frames).toHaveCount(1);
    const actions = page.frameLocator('#iframe-tabContent iframe').first();
    await expect(actions.getByTestId('demo-actions')).toBeVisible();
    const opener = actions.getByRole('link', { name: '标签详情', exact: true }).first();
    const target = await opener.getAttribute('data-to-url');
    await opener.click();
    await expect(frames).toHaveCount(2);
    await expect(page.locator('#iframe-tabContent .tab-pane.active')).toHaveCount(1);
    const detail = page.frameLocator('#iframe-tabContent .tab-pane.active iframe');
    await expect(detail.locator('section.content-header')).toContainText('记录详情');
    await expect(page.locator('#iframe-tabContent .tab-pane.active iframe')).toHaveAttribute('src', target);
    await expect(page).toHaveURL(/\/admin\/demo\/tabs$/);

    await page.locator('#iframe-tab .nav-link[data-first="1"]').click();
    await expect(actions.getByTestId('demo-actions')).toBeVisible();
    await expect(page.locator('#iframe-tabContent .tab-pane.active')).toHaveCount(1);
    await opener.click();
    await expect(frames).toHaveCount(2);
    await expect(page.locator('#iframe-tabContent .tab-pane.active')).toHaveCount(1);
    await expect(detail.locator('section.content-header')).toContainText('记录详情');
    await page.locator('#iframe-tab .nav-link.active').hover();
    await page.locator('#iframe-tab .nav-link.active .iframe-tab-close-btn').click();
    await expect(frames).toHaveCount(1);
    await expect(actions.getByTestId('demo-actions')).toBeVisible();
});

test('8 个全局开关改变真实组件效果且不污染默认请求', async ({ page }) => {
    test.setTimeout(180_000);
    await enterDemo(page);
    await page.goto('/admin/demo/records');
    const editPath = await page.locator('#grid-table tbody tr').first().getByRole('link', { name: '编辑', exact: true }).getAttribute('href');
    const id = editPath.match(/\/(\d+)\/edit$/)[1];
    const container = page.getByTestId('live-example');
    const kitAsset = name => page.locator(`link[href*="dcat-admin-kit"][href*="/${name}"], script[src*="dcat-admin-kit"][src*="/${name}"]`);

    const cases = [
        {
            feature: 'grid_defaults', path: '/admin/demo/preview', baseline: '/admin/demo/records',
            async check(enabled) {
                const row = page.locator('#grid-table tbody tr').first();
                // Site-wide grid_assets stays on outside its own comparison page.
                await expect(page.locator('#grid-table thead th').first()).toHaveCSS('white-space', 'nowrap');
                await expect(row.locator('.grid-row-checkbox')).toHaveCount(enabled ? 0 : 1);
                await expect(row.locator('a').filter({ hasText: /^\s*显示\s*$/ })).toHaveCount(enabled ? 0 : 1);
                await expect(row.locator('[data-action="delete"]')).toHaveCount(enabled ? 0 : 1);
                if (enabled) await expect(container.locator('.grid-refresh')).not.toHaveClass(/btn-outline/);
                else await expect(container.locator('.grid-refresh')).toHaveClass(/btn-outline/);
            },
        },
        {
            feature: 'form_defaults', path: '/admin/demo/preview-form', baseline: `/admin/demo/records/${id}/edit`,
            async check(enabled) {
                await expect(container.locator('[data-action="delete"]')).toHaveCount(enabled ? 0 : 1);
                await expect(container.getByRole('link', { name: /查看$/ })).toHaveCount(enabled ? 0 : 1);
                await expect(container.getByRole('link', { name: /列表$/ })).toHaveCount(enabled ? 0 : 1);
                await expect(container.locator('input[name="after-save"][value="1"], input[name="after-save"][value="3"]')).toHaveCount(enabled ? 0 : 2);
            },
        },
        {
            feature: 'show_defaults', path: `/admin/demo/preview-show/${id}`, baseline: `/admin/demo/records/${id}`,
            async check(enabled) {
                await expect(container.getByRole('link', { name: /编辑$/ })).toHaveCount(enabled ? 0 : 1);
                await expect(container.locator('[data-action="delete"]')).toHaveCount(enabled ? 0 : 1);
            },
        },
        {
            feature: 'right_side_filter', path: '/admin/demo/preview', baseline: '/admin/demo/records',
            async check(enabled) {
                // Dcat 2.2.4 already uses a right drawer. Kit removes its fixed
                // header and adds a second submit button below the fields.
                await expect(page.locator('.right-side-filter-container')).toHaveCount(1);
                await container.getByRole('button', { name: /筛选/ }).click();
                const filter = page.locator('.slider-panel .grid-filter-form');
                await expect(filter).toBeVisible();
                await expect(filter.locator('input[name="title"]')).toBeVisible();
                await expect(filter.locator('button.submit')).toHaveCount(enabled ? 2 : 1);
                await expect(filter.locator('.header.position-fixed')).toHaveCount(enabled ? 0 : 1);
            },
        },
        {
            feature: 'top_form_tools', path: '/admin/demo/preview-form', baseline: '/admin/demo/preview-form',
            async check(enabled) {
                await expect(container.getByRole('link', { name: /提交$/ })).toHaveCount(enabled ? 1 : 0);
                await expect(container.getByRole('link', { name: /返回$/ })).toHaveCount(enabled ? 1 : 0);
                await expect(container.locator('button.submit')).toHaveCount(1);
            },
        },
        {
            feature: 'back_to_top', path: '/admin/demo/preview', baseline: '/admin/demo/records',
            async check(enabled) {
                const back = page.locator('#dcat-admin-kit-back-to-top');
                await expect(back).toHaveCount(enabled ? 1 : 0);
                if (enabled) {
                    await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
                    await expect(back).toBeVisible();
                    await back.click();
                    await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
                }
            },
        },
        {
            // Enabled site-wide through config/dcat-admin-kit.php, so the plain pages carry it too.
            feature: 'grid_assets', path: '/admin/demo/preview', baseline: '/admin/demo/records', baselineEnabled: true,
            async check(enabled, onBaseline = false) {
                await expect(page.locator('table.table-head-fixed')).toHaveCount(enabled ? 1 : 0);
                for (const name of ['grid.css', 'grid.js', 'jquery.nicescroll.min.js']) {
                    await expect(kitAsset(name)).toHaveCount(enabled ? 1 : 0);
                }
                if (enabled) {
                    // Header labels stay on one line instead of wrapping the row taller.
                    await expect(page.locator('#grid-table thead th').first()).toHaveCSS('white-space', 'nowrap');
                    // Only the wide-table layout has .table-main to host NiceScroll.
                    if (! onBaseline) {
                        await expect.poll(() => page.evaluate(() => window.jQuery('#grid-table').parent('.table-main').getNiceScroll().length)).toBe(1);
                    }
                }
            },
        },
        {
            feature: 'global_styles', path: '/admin/demo/preview', baseline: '/admin/demo/records',
            async check(enabled) {
                await expect(kitAsset('global.css')).toHaveCount(enabled ? 1 : 0);
                if (enabled) {
                    await expect(page.locator('.content-header h1')).toHaveCSS('font-size', '22px');
                    await expect(page.locator('.main-footer')).toBeHidden();
                } else {
                    await expect(page.locator('.main-footer')).toBeVisible();
                }
            },
        },
    ];

    for (const { feature, path, baseline, baselineEnabled = false, check } of cases) {
        await test.step(feature, async () => {
            await page.goto(`${path}?feature=${feature}&enabled=0`);
            for (const enabled of [false, true, false]) {
                await page.evaluate(() => { window.__demoPreviewMarker = 'old-document'; });
                await page.getByTestId(enabled ? 'preview-on' : 'preview-off').click();
                await expect(page.getByTestId('demo-preview')).toHaveAttribute('data-enabled', enabled ? '1' : '0');
                await expect.poll(() => page.evaluate(() => window.__demoPreviewMarker)).toBeUndefined();
                await check(enabled);
            }
            // A separate normal request must retain the default behavior.
            await page.goto(baseline);
            await check(baselineEnabled, true);
        });
    }
});
