<?php

namespace App\Models;

use App\Enums\KategoriFoto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderFoto extends Model
{
    protected $table = 'order_foto';

    protected $fillable = ['order_id', 'kategori', 'path', 'diunggah_oleh'];

    protected function casts(): array
    {
        return ['kategori' => KategoriFoto::class];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderServis::class, 'order_id');
    }
}
