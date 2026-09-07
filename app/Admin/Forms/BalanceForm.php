<?php

namespace App\Admin\Forms;

use App\Models\DemoRecord;
use App\Services\DemoData;
use Dcat\Admin\Contracts\LazyRenderable;
use Dcat\Admin\Traits\LazyWidget;
use Dcat\Admin\Widgets\Form;
use Illuminate\Support\Facades\Validator;

class BalanceForm extends Form implements LazyRenderable
{
    use LazyWidget;

    public function form()
    {
        $id = $this->payload['id'] ?? null;
        $record = DemoRecord::query()->findOrFail($id);
        $this->html('<p>记录 #'.e($record->id).' · '.e($record->title).
            ' · 当前演示余额 <strong>'.e($record->balance).'</strong></p>');
        $this->hidden('id')->value($id);
        $this->hidden('action')->value($this->payload['action'] ?? 'balance');
        $this->decimal('amount', '增加金额')->required()->default('10.00')
            ->rules('required|numeric|min:0.01|max:10000|decimal:0,2')
            ->help('仅增加演示余额，不涉及真实资金。单次 0.01–10,000，最多两位小数。');
        $this->disableResetButton();
    }

    public function handle(array $input)
    {
        $data = Validator::make($input, [
            'action' => 'required|in:balance',
            'id' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0.01|max:10000|decimal:0,2',
        ], [], ['id' => '记录', 'action' => '操作', 'amount' => '增加金额'])->validate();

        app(DemoData::class)->addBalance((int) $data['id'], $data['amount']);

        return $this->response()->success('演示余额已增加，操作已记录')->refresh();
    }
}
