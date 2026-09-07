<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DemoRecord extends Model
{
    protected $fillable = ['title', 'code', 'url', 'description', 'status', 'balance'];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected function casts(): array
    {
        return ['status' => 'boolean', 'balance' => 'decimal:2'];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DemoLog::class);
    }
}
