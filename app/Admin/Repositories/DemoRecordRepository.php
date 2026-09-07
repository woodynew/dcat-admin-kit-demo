<?php

namespace App\Admin\Repositories;

use App\Models\DemoRecord;
use App\Services\DemoData;
use Dcat\Admin\Form;
use Dcat\Admin\Repositories\EloquentRepository;
use Illuminate\Support\Arr;

class DemoRecordRepository extends EloquentRepository
{
    protected $eloquentClass = DemoRecord::class;

    public function store(Form $form)
    {
        $this->model = app(DemoData::class)->create($this->attributes($form));

        return $this->model->getKey();
    }

    public function update(Form $form)
    {
        // EloquentRepository::updating already loaded this snapshot before validation.
        $this->model = app(DemoData::class)->update($this->model(), $this->attributes($form));

        return true;
    }

    public function delete(Form $form, array $originalData)
    {
        foreach ($this->collection as $record) {
            app(DemoData::class)->delete($record);
        }

        return true;
    }

    private function attributes(Form $form): array
    {
        // Balance is changed only by the service's dedicated addBalance operation.
        return Arr::only($form->updates(), ['title', 'code', 'url', 'description', 'status']);
    }
}
