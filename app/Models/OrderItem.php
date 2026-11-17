<?php

namespace App\Models;

use App\Enums\JenisItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $table = 'order_item';

    protected $fillable = ['order_id', 'jenis', 'layanan_id', 'barang_id', 'nama', 'qty', 'harga', 'subtotal', 'dibuat_oleh'];

    protected function casts(): array
    {
        return [
            'jenis' => JenisItem::class,
            'qty' => 'float',
            'harga' => 'integer',
            'subtotal' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(OrderServis::class, 'order_id');
    }

    public function layanan(): BelongsTo
    {
        return $this->belongsTo(Layanan::class)->withTrashed();
    }

    public function barang(): BelongsTo
    {
        return $this->belongsTo(Barang::class)->withTrashed();
    }

    /** qty tanpa ".00" untuk tampilan. */
    public function qtyTampil(): string
    {
        return rtrim(rtrim(number_format($this->qty, 2, ',', '.'), '0'), ',');
    }
}
