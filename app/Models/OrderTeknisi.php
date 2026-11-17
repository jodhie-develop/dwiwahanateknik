<?php

namespace App\Models;

use App\Enums\PeranTim;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderTeknisi extends Model
{
    protected $table = 'order_teknisi';

    protected $fillable = ['order_id', 'teknisi_id', 'peran'];

    protected function casts(): array
    {
        return ['peran' => PeranTim::class];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderServis::class, 'order_id');
    }

    public function teknisi(): BelongsTo
    {
        return $this->belongsTo(Teknisi::class)->withTrashed();
    }
}
