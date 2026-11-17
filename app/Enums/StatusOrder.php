<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Status order servis + aturan transisi. SATU-SATUNYA tempat aturan alur status.
 *
 * Kunjungan: dijadwalkan → berangkat → dikerjakan → selesai; batal dari status mana pun sebelum selesai.
 * Bengkel:   diterima → diagnosa → menunggu_konfirmasi → dikerjakan ⇄ menunggu_sparepart → selesai → diambil;
 *            menunggu_konfirmasi → batal → dikembalikan.
 */
enum StatusOrder: string
{
    use EnumHelpers;

    // Kunjungan
    case Dijadwalkan = 'dijadwalkan';
    case Berangkat = 'berangkat';

    // Bengkel
    case Diterima = 'diterima';
    case Diagnosa = 'diagnosa';
    case MenungguKonfirmasi = 'menunggu_konfirmasi';
    case MenungguSparepart = 'menunggu_sparepart';
    case Diambil = 'diambil';
    case Dikembalikan = 'dikembalikan';

    // Bersama
    case Dikerjakan = 'dikerjakan';
    case Selesai = 'selesai';
    case Batal = 'batal';

    public function label(): string
    {
        return match ($this) {
            self::Dijadwalkan => 'Dijadwalkan',
            self::Berangkat => 'Berangkat',
            self::Diterima => 'Diterima',
            self::Diagnosa => 'Diagnosa',
            self::MenungguKonfirmasi => 'Menunggu konfirmasi',
            self::MenungguSparepart => 'Menunggu sparepart',
            self::Diambil => 'Diambil',
            self::Dikembalikan => 'Dikembalikan',
            self::Dikerjakan => 'Dikerjakan',
            self::Selesai => 'Selesai',
            self::Batal => 'Batal',
        };
    }

    /** Warna badge Flux. */
    public function warna(): string
    {
        return match ($this) {
            self::Dijadwalkan, self::Diterima => 'zinc',
            self::Berangkat, self::Diagnosa => 'sky',
            self::Dikerjakan => 'blue',
            self::MenungguKonfirmasi, self::MenungguSparepart => 'amber',
            self::Selesai, self::Diambil => 'green',
            self::Batal, self::Dikembalikan => 'red',
        };
    }

    /** @return list<StatusOrder> status tujuan yang sah dari status ini, untuk tipe order tertentu. */
    public function tujuanSah(TipeOrder $tipe): array
    {
        return match ($tipe) {
            TipeOrder::Kunjungan => match ($this) {
                self::Dijadwalkan => [self::Berangkat, self::Batal],
                self::Berangkat => [self::Dikerjakan, self::Batal],
                self::Dikerjakan => [self::Selesai, self::Batal],
                default => [],
            },
            TipeOrder::Bengkel => match ($this) {
                self::Diterima => [self::Diagnosa],
                self::Diagnosa => [self::MenungguKonfirmasi],
                self::MenungguKonfirmasi => [self::Dikerjakan, self::Batal],
                self::Dikerjakan => [self::MenungguSparepart, self::Selesai],
                self::MenungguSparepart => [self::Dikerjakan],
                self::Selesai => [self::Diambil],
                self::Batal => [self::Dikembalikan],
                default => [],
            },
        };
    }

    public function bolehKe(StatusOrder $tujuan, TipeOrder $tipe): bool
    {
        return in_array($tujuan, $this->tujuanSah($tipe), true);
    }

    /** Order sudah final: item, tim, dan teknisi tidak boleh diubah lagi. */
    public function terkunci(): bool
    {
        return in_array($this, [self::Selesai, self::Diambil, self::Batal, self::Dikembalikan], true);
    }

    /** Status yang menandakan tim kunjungan sudah mulai bergerak hari itu. */
    public static function tandaBergerak(): array
    {
        return [self::Berangkat, self::Dikerjakan, self::Selesai];
    }

    /** @return list<StatusOrder> status milik tipe tertentu, untuk filter. */
    public static function untukTipe(TipeOrder $tipe): array
    {
        return match ($tipe) {
            TipeOrder::Kunjungan => [self::Dijadwalkan, self::Berangkat, self::Dikerjakan, self::Selesai, self::Batal],
            TipeOrder::Bengkel => [
                self::Diterima, self::Diagnosa, self::MenungguKonfirmasi, self::Dikerjakan,
                self::MenungguSparepart, self::Selesai, self::Diambil, self::Batal, self::Dikembalikan,
            ],
        };
    }
}
