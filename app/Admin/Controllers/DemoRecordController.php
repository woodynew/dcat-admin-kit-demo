<?php

namespace App\Admin\Controllers;

use App\Models\DemoRecord;
use App\Support\DemoExamples;
use Dcat\Admin\Http\Controllers\AdminController;
use Dcat\Admin\Layout\Content;

class DemoRecordController extends AdminController
{
    protected $title = '共享记录';

    public function index(Content $content)
    {
        return $this->page($content, __('共享记录'),
            __('所有访客共享这组记录。新增、编辑、复制和快捷状态更新都实际保存，每小时统一重置。'), $this->grid());
    }

    public function create(Content $content)
    {
        $copy = request()->query('copy');
        abort_if($copy !== null && (! is_scalar($copy) || ! ctype_digit((string) $copy)), 422, __('复制来源无效。'));
        $source = $copy ? DemoRecord::query()->findOrFail($copy) : null;

        return $this->page($content, $source ? __('复制为新记录') : __('创建记录'),
            $source ? __('已预填来源记录的内容。新编码已生成，余额从 0 开始；点击提交后才会创建。')
                : __('填写内容后使用右上方「提交」或表单底部的原生提交按钮。'),
            DemoExamples::recordForm(true, DemoExamples::copyDefaults($source)));
    }

    public function edit($id, Content $content)
    {
        return $this->page($content, __('编辑记录 #').(int) $id,
            __('完整保存后，下方日志会显示 AdminFormUtil = true；列表内快捷状态更新为 false。'), $this->form()->edit($id));
    }

    public function show($id, Content $content)
    {
        return $this->page($content, __('记录详情 #').(int) $id,
            __('当前共享记录的真实数据。返回编辑页可以体验复制、顶部返回和原生提交。'), $this->detail($id));
    }

    protected function grid()
    {
        return DemoExamples::recordsGrid();
    }

    protected function form()
    {
        return DemoExamples::recordForm();
    }

    protected function detail($id)
    {
        return DemoExamples::recordShow((int) $id);
    }

    private function page(Content $content, string $title, string $description, $example)
    {
        return $content->title($title)->description(__('可验证的表单交互'))
            ->body(view('demo.page', compact('title', 'description', 'example') + [
                'section' => 'records', 'withLogs' => true,
            ]));
    }
}
