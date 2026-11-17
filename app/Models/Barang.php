<?php

namespace App\Models;

use App\Models\Concerns\PunyaStatusAktif;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Master barang bersifat global; stok per cabang ditangani di Fase 4. */
class Barang extends Model
{
    use HasFactory, PunyaStatusAktif, SoftDeletes;

    protected $table = 'barang';

    protected $fillable = ['kode', 'nama', 'satuan', 'harga_jual', 'aktif'];

    protected function casts(): array
    {
        return ['harga_jual' => 'integer'];
    }
}
