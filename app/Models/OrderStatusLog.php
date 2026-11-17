<?php

namespace App\Models;

use App\Enums\StatusOrder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderStatusLog extends Model
{
    protected $table = 'order_status_log';

    protected $fillable = ['order_id', 'dari_status', 'ke_status', 'user_id', 'catatan'];

    protected function casts(): array
    {
        return [
            'dari_status' => StatusOrder::class,
            'ke_status' => StatusOrder::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
