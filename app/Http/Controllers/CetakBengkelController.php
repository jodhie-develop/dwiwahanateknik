<?php

namespace App\Http\Controllers;

use App\Enums\TipeOrder;
use App\Models\OrderServis;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Halaman cetak (kertas A4/A5 biasa) untuk servis bengkel. */
class CetakBengkelController extends Controller
{
    /** Tanda terima ukuran A5, dicetak 2 rangkap dalam 1 lembar A4 (pelanggan & arsip). */
    public function tandaTerima(OrderServis $order): View
    {
        $this->siapkan($order);

        return view('cetak.tanda-terima', ['o' => $order]);
    }

    /** Label nomor servis, beberapa per lembar A4 untuk digunting & ditempel. */
    public function label(Request $request, OrderServis $order): View
    {
        $this->siapkan($order);

        return view('cetak.label', [
            'o' => $order,
            'jumlah' => min(24, max(1, $request->integer('jumlah', 3))),
        ]);
    }

    private function siapkan(OrderServis $order): void
    {
        abort_unless($order->tipe === TipeOrder::Bengkel, 404);
        Gate::authorize('view', $order);

        $order->load(['pelanggan', 'unit.jenisPerangkat', 'penerimaan.penerima:id,nama', 'pembuat:id,nama', 'cabang', 'item']);
    }
}
