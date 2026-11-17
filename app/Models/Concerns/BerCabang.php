<?php

namespace App\Models\Concerns;

use App\Models\Cabang;
use App\Models\Scopes\CabangScope;
use App\Support\KonteksCabang;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Untuk model yang punya kolom cabang_id.
 * - Query otomatis dibatasi ke cabang user (lihat CabangScope).
 * - Saat menyimpan, user non-owner selalu dipaksa ke cabangnya sendiri.
 */
trait BerCabang
{
    public static function bootBerCabang(): void
    {
        static::addGlobalScope(new CabangScope);

        static::saving(function ($model) {
            $user = KonteksCabang::user();

            if ($user && ! $user->isOwner()) {
                $model->cabang_id = $user->cabang_id;
            }
        });
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }
}
