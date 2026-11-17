<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait PunyaStatusAktif
{
    public function initializePunyaStatusAktif(): void
    {
        $this->mergeCasts(['aktif' => 'boolean']);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('aktif'), true);
    }
}
