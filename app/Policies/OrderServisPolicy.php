<?php

namespace App\Policies;

use App\Enums\StatusOrder;
use App\Models\OrderServis;
use App\Models\User;
use App\Policies\Concerns\CekPeran;

/**
 * - Owner/admin cabang: kelola order di cabangnya (owner semua cabang).
 * - Teknisi: hanya order yang ia kerjakan; boleh ubah status pengerjaan, item, foto, tanda tangan,
 *   estimasi & mencatat persetujuan pelanggan. Pembatalan, serah terima barang, dan penugasan hanya oleh admin.
 */
class OrderServisPolicy
{
    use CekPeran;

    public function viewAny(User $user): bool
    {
        return $this->staf($user);
    }

    public function view(User $user, OrderServis $order): bool
    {
        return $this->stafDiCabang($user, $order->cabang_id) || $this->pelaksana($user, $order);
    }

    public function create(User $user): bool
    {
        return $this->staf($user);
    }

    public function update(User $user, OrderServis $order): bool
    {
        return $this->stafDiCabang($user, $order->cabang_id);
    }

    /** Atur tim, teknisi pelaksana, diskon. */
    public function aturPenugasan(User $user, OrderServis $order): bool
    {
        return $this->stafDiCabang($user, $order->cabang_id);
    }

    public function ubahStatus(User $user, OrderServis $order, ?StatusOrder $ke = null): bool
    {
        if ($this->stafDiCabang($user, $order->cabang_id)) {
            return true;
        }

        // Pembatalan & serah terima barang ke pelanggan hanya oleh admin.
        $khususAdmin = [StatusOrder::Batal, StatusOrder::Diambil, StatusOrder::Dikembalikan];

        return ! in_array($ke, $khususAdmin, true) && $this->pelaksana($user, $order);
    }

    /** Item, foto, check-in, tanda tangan. */
    public function kerjakan(User $user, OrderServis $order): bool
    {
        return $this->stafDiCabang($user, $order->cabang_id) || $this->pelaksana($user, $order);
    }

    private function pelaksana(User $user, OrderServis $order): bool
    {
        return $user->isTeknisi() && $order->dikerjakanOlehUser($user);
    }
}
