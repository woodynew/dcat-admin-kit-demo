<?php

namespace App\Admin\Forms;

use App\Services\DemoData;
use Dcat\Admin\Widgets\Form;
use Illuminate\Support\Facades\Validator;

class BulkNoticeForm extends Form
{
    public function form()
    {
        $this->hidden('action')->value($this->data()->get('action', 'notice'));
        $this->textarea('content', '通知内容')->required()->rules('required|string|max:500')
            ->help('通知写入共享操作日志；演示站不向外部发送消息。最多 500 字。');
        $this->disableResetButton();
    }

    public function handle(array $input)
    {
        $data = Validator::make($input, [
            'action' => 'required|in:notice',
            'content' => 'required|string|max:500',
        ], [], ['action' => '操作', 'content' => '通知内容'])->validate();

        app(DemoData::class)->notify($data['content']);

        return $this->response()->success('通知已写入共享日志')->refresh();
    }
}
