<?php

namespace App\Models;

use App\Enums\StatusBayar;
use App\Enums\StatusOrder;
use App\Enums\TipeOrder;
use App\Models\Concerns\BerCabang;
use App\Models\Scopes\JobTeknisiScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Order servis (kunjungan & bengkel).
 * Perubahan status WAJIB lewat App\Services\OrderService::ubahStatus agar tervalidasi & tercatat di log.
 */
class OrderServis extends Model
{
    use BerCabang, HasFactory;

    protected $table = 'order_servis';

    protected $fillable = [
        'cabang_id', 'tipe', 'pelanggan_id', 'unit_pelanggan_id', 'keluhan', 'tgl_jadwal', 'jam_janji',
        'diskon', 'catatan', 'order_garansi_dari_id',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new JobTeknisiScope);
    }

    protected function casts(): array
    {
        return [
            'tipe' => TipeOrder::class,
            'status' => StatusOrder::class,
            'status_bayar' => StatusBayar::class,
            'tgl_jadwal' => 'date',
            'ditugaskan_at' => 'datetime',
            'checkin_at' => 'datetime',
            'selesai_at' => 'datetime',
            'diambil_at' => 'datetime',
            'checkin_lat' => 'float',
            'checkin_lng' => 'float',
            'subtotal' => 'integer',
            'diskon' => 'integer',
            'total' => 'integer',
        ];
    }

    // ---------------- Relasi ----------------

    public function pelanggan(): BelongsTo
    {
        return $this->belongsTo(Pelanggan::class)->withTrashed();
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(UnitPelanggan::class, 'unit_pelanggan_id')->withTrashed();
    }

    public function tim(): BelongsTo
    {
        return $this->belongsTo(Tim::class)->withTrashed();
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh')->withTrashed();
    }

    public function teknisi(): HasMany
    {
        return $this->hasMany(OrderTeknisi::class, 'order_id');
    }

    public function item(): HasMany
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }

    public function foto(): HasMany
    {
        return $this->hasMany(OrderFoto::class, 'order_id');
    }

    public function log(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class, 'order_id');
    }

    public function penerimaan(): HasOne
    {
        return $this->hasOne(OrderPenerimaan::class, 'order_id');
    }

    public function estimasi(): HasMany
    {
        return $this->hasMany(OrderEstimasi::class, 'order_id');
    }

    /** Estimasi yang berlaku (paling akhir dibuat). */
    public function estimasiTerakhir(): HasOne
    {
        return $this->hasOne(OrderEstimasi::class, 'order_id')->latestOfMany();
    }

    // ---------------- Scope ----------------

    public function scopeKunjungan(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('tipe'), TipeOrder::Kunjungan->value);
    }

    public function scopeBengkel(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('tipe'), TipeOrder::Bengkel->value);
    }

    /** Barang bengkel berstatus selesai yang belum diambil lebih dari $batasHari hari. */
    public function scopeBelumDiambilLewat(Builder $query, int $batasHari): Builder
    {
        return $query->bengkel()
            ->where($this->qualifyColumn('status'), StatusOrder::Selesai->value)
            ->where($this->qualifyColumn('selesai_at'), '<=', now()->subDays($batasHari));
    }

    public function scopePadaTanggal(Builder $query, string $tanggal): Builder
    {
        return $query->whereDate($this->qualifyColumn('tgl_jadwal'), $tanggal);
    }

    /** Urutan antrian: waktu input admin (paling awal dikerjakan lebih dulu). */
    public function scopeUrutAntrian(Builder $query): Builder
    {
        return $query->orderBy($this->qualifyColumn('created_at'))->orderBy($this->qualifyColumn('id'));
    }

    public function scopeTidakBatal(Builder $query): Builder
    {
        return $query->whereNotIn($this->qualifyColumn('status'), [StatusOrder::Batal->value, StatusOrder::Dikembalikan->value]);
    }

    // ---------------- Helper ----------------

    public function terkunci(): bool
    {
        return $this->status->terkunci();
    }

    /** Halaman detail admin sesuai tipe order. */
    public function urlDetail(): string
    {
        return $this->tipe === TipeOrder::Bengkel
            ? route('bengkel.detail', $this)
            : route('order.detail', $this);
    }

    /** @return array{lat: float, lng: float}|null */
    public function koordinat(): ?array
    {
        return $this->unit?->koordinatEfektif();
    }

    public function dikerjakanOlehUser(User $user): bool
    {
        return $this->teknisi()
            ->whereHas('teknisi', fn ($q) => $q->withoutGlobalScopes()->where('user_id', $user->id))
            ->exists();
    }
}
