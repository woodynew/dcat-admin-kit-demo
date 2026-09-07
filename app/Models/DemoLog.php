<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoLog extends Model
{
    protected $fillable = ['demo_record_id', 'action', 'message'];

    public function record(): BelongsTo
    {
        return $this->belongsTo(DemoRecord::class, 'demo_record_id');
    }
}
