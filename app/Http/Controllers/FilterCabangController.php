<?php

namespace App\Http\Controllers;

use App\Models\Cabang;
use App\Support\KonteksCabang;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Owner memilih cabang yang ingin dilihat (kosong = semua cabang). */
class FilterCabangController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        abort_unless($request->user()->isOwner(), 403);

        $data = $request->validate([
            'cabang_id' => ['nullable', 'integer', Rule::exists(Cabang::class, 'id')->whereNull('deleted_at')],
        ]);

        KonteksCabang::setFilterOwner($data['cabang_id'] ?? null);

        return back();
    }
}
