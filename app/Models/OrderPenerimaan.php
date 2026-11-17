<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tanda terima barang servis bengkel. */
class OrderPenerimaan extends Model
{
    protected $table = 'order_penerimaan';

    protected $fillable = ['order_id', 'kondisi_fisik', 'kelengkapan', 'catatan_kerusakan', 'lokasi_simpan', 'diterima_oleh'];

    protected function casts(): array
    {
        return ['kelengkapan' => 'array'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderServis::class, 'order_id');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_oleh')->withTrashed();
    }

    public function kelengkapanTeks(): string
    {
        return collect($this->kelengkapan ?? [])->filter()->implode(', ') ?: '-';
    }
}
