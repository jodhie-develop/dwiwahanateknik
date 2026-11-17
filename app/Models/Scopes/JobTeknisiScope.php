<?php

namespace App\Models\Scopes;

use App\Support\KonteksCabang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** User ber-role teknisi hanya bisa melihat order yang ia kerjakan (tercatat di order_teknisi). */
class JobTeknisiScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = KonteksCabang::user();

        if (! $user || ! $user->isTeknisi()) {
            return;
        }

        $builder->whereExists(fn ($q) => $q->selectRaw('1')
            ->from('order_teknisi')
            ->join('teknisi', 'teknisi.id', '=', 'order_teknisi.teknisi_id')
            ->whereColumn('order_teknisi.order_id', $model->qualifyColumn('id'))
            ->where('teknisi.user_id', $user->id));
    }
}
