<?php

use App\Database\PostgresConnection;

function koneksiPostgres(bool $emulasi): PostgresConnection
{
    // PDO tidak pernah dibuat: prepareBindings tidak butuh koneksi aktif.
    return new PostgresConnection(fn () => null, 'db', '', [
        'options' => $emulasi ? [PDO::ATTR_EMULATE_PREPARES => true] : [],
    ]);
}

test('mode emulasi prepare mengirim boolean sebagai literal true/false', function () {
    expect(koneksiPostgres(true)->prepareBindings([true, false, 5, 'x']))
        ->toBe(['true', 'false', 5, 'x']);
});

test('tanpa emulasi perilaku bawaan Laravel tetap', function () {
    expect(koneksiPostgres(false)->prepareBindings([true, false]))->toBe([1, 0]);
});

test('koneksi pgsql memakai kelas koneksi aplikasi', function () {
    config(['database.connections.pgsql_uji' => array_merge(config('database.connections.pgsql'), ['host' => '127.0.0.1'])]);

    expect(DB::connection('pgsql_uji'))->toBeInstanceOf(PostgresConnection::class);
});
