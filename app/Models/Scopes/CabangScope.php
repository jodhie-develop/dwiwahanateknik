<?php

namespace App\Models\Scopes;

use App\Support\KonteksCabang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CabangScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $cabangId = KonteksCabang::idPembatas();

        if ($cabangId !== null) {
            $builder->where($model->qualifyColumn('cabang_id'), $cabangId);
        }
    }
}
