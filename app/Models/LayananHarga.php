<?php

namespace App\Models;

use App\Models\Concerns\BerCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LayananHarga extends Model
{
    use BerCabang;

    protected $table = 'layanan_harga';

    protected $fillable = ['layanan_id', 'cabang_id', 'harga'];

    protected function casts(): array
    {
        return ['harga' => 'integer'];
    }

    public function layanan(): BelongsTo
    {
        return $this->belongsTo(Layanan::class);
    }
}
