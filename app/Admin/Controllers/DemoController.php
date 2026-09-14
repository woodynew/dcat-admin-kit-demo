<?php

namespace App\Admin\Controllers;

use App\Models\DemoLog;
use App\Models\DemoRecord;
use App\Models\DemoState;
use App\Support\DemoExamples;
use Dcat\Admin\Layout\Content;
use Illuminate\Routing\Controller;

class DemoController extends Controller
{
    public function overview(Content $content)
    {
        return $content->title(__('演示概览'))->description(__('Dcat Admin Kit · 交互式组件手册'))
            ->body(view('demo.overview', [
                'lastResetAt' => DemoState::query()->where('key', 'last_reset_at')->value('value'),
                'recordCount' => DemoRecord::query()->count(),
                'logCount' => DemoLog::query()->count(),
            ]));
    }

    public function columns(Content $content)
    {
        return $this->page($content, 'columns', __('列显示器'),
            __('点击二维码、复制链接、展开长文本。中文、引号、特殊字符与空值都在同一张真实 Grid 中。'),
            DemoExamples::columnsGrid());
    }

    public function actions(Content $content)
    {
        return $this->page($content, 'actions', __('操作与工具'),
            request()->query('iframe') === '1'
                ? __('当前为紧凑子页面。点击「标签详情」在父容器打开记录，再次点击同一记录复用标签。')
                : __('试着发布一条通知、增加演示余额或打开记录详情。「标签详情」在普通页面中直接跳转。'),
            DemoExamples::recordsGrid(), true);
    }

    public function widgets(Content $content)
    {
        return $this->page($content, 'widgets', __('组件与汇总'),
            __('两张独立的 PostTable 分别呈现当前记录与最新日志，所有内容来自共享演示数据。'),
            DemoExamples::postTable(), true);
    }

    public function features(Content $content)
    {
        return $content->title(__('全局特性'))->description(__('8 个开关 · 独立请求对照'))
            ->body(view('demo.features', ['features' => DemoExamples::features(),
                'recordId' => DemoRecord::query()->orderBy('id')->value('id')]));
    }

    public function preview(Content $content)
    {
        return $this->previewPage($content, DemoExamples::recordsGrid(false));
    }

    public function previewForm(Content $content)
    {
        $id = DemoRecord::query()->orderBy('id')->value('id');
        $form = DemoExamples::recordForm(false, DemoExamples::copyDefaults());
        // Dcat's Form::resource slices a full create/edit path for native toolbar URLs.
        $form->setResource(admin_url('demo/records'.($id ? '/'.$id.'/edit' : '/create')));
        $form->action(admin_url('demo/records'.($id ? '/'.$id : '')));
        if ($id) {
            $form->edit($id);
        }

        return $this->previewPage($content, $form);
    }

    public function previewShow($id, Content $content)
    {
        return $this->previewPage($content, DemoExamples::recordShow((int) $id));
    }

    private function previewPage(Content $content, $example)
    {
        $feature = request()->query('feature', 'grid_defaults');
        abort_unless(is_string($feature) && isset(DemoExamples::features()[$feature]), 404);
        $enabled = request()->query('enabled') === '1';

        return $content->title(__('特性对照 · ').DemoExamples::features()[$feature][0])
            ->description($enabled ? __('当前请求：开启') : __('当前请求：关闭'))
            ->body(view('demo.preview', compact('feature', 'enabled', 'example') + [
                'features' => DemoExamples::features(),
                'recordId' => DemoRecord::query()->orderBy('id')->value('id'),
            ]));
    }

    private function page(Content $content, string $section, string $title, string $description, $example, bool $withLogs = false)
    {
        return $content->title($title)->description(__('可操作示例 · 参数说明 · 真实源码'))
            ->body(view('demo.page', compact('section', 'title', 'description', 'example', 'withLogs')));
    }
}
