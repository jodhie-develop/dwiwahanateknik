<?php

namespace App\Database;

use Illuminate\Database\PostgresConnection as KoneksiPostgresBawaan;
use PDO;

/**
 * Koneksi PostgreSQL yang aman untuk PDO::ATTR_EMULATE_PREPARES (transaction pooler Supabase, port 6543).
 *
 * Bawaan Laravel mengubah boolean menjadi integer (true → 1). Dengan prepared statement asli, Postgres
 * menyimpulkan tipenya sendiri; tetapi saat emulasi, nilai ditempel langsung ke SQL (`aktif = 1`)
 * dan Postgres menolak "operator does not exist: boolean = integer". Di mode emulasi, boolean dikirim
 * sebagai literal string 'true'/'false' yang dikenali Postgres untuk kolom boolean.
 */
class PostgresConnection extends KoneksiPostgresBawaan
{
    public function prepareBindings(array $bindings)
    {
        if ($this->memakaiEmulasiPrepare()) {
            foreach ($bindings as $kunci => $nilai) {
                if (is_bool($nilai)) {
                    $bindings[$kunci] = $nilai ? 'true' : 'false';
                }
            }
        }

        return parent::prepareBindings($bindings);
    }

    protected function memakaiEmulasiPrepare(): bool
    {
        return (bool) ($this->getConfig('options')[PDO::ATTR_EMULATE_PREPARES] ?? false);
    }
}
