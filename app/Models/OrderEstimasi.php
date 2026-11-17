<?php

namespace App\Models;

use App\Enums\MediaPersetujuan;
use App\Enums\StatusEstimasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Estimasi biaya servis bengkel + keputusan pelanggan. */
class OrderEstimasi extends Model
{
    protected $table = 'order_estimasi';

    protected $fillable = [
        'order_id', 'nominal', 'catatan', 'status', 'media', 'diputuskan_at', 'catatan_keputusan', 'dibuat_oleh', 'dicatat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'integer',
            'status' => StatusEstimasi::class,
            'media' => MediaPersetujuan::class,
            'diputuskan_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderServis::class, 'order_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh')->withTrashed();
    }

    public function pencatat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dicatat_oleh')->withTrashed();
    }
}
