<?php

use Carbon\Carbon;

test('format rupiah tanpa desimal', function () {
    expect(rupiah(1250000))->toBe('Rp 1.250.000')
        ->and(rupiah(0))->toBe('Rp 0')
        ->and(rupiah(null))->toBe('Rp 0')
        ->and(rupiah(-5000))->toBe('Rp -5.000')
        ->and(rupiah(75000, false))->toBe('75.000');
});

test('format tanggal d/m/Y', function () {
    expect(tgl(Carbon::create(2026, 11, 5)))->toBe('05/11/2026')
        ->and(tgl('2026-01-31'))->toBe('31/01/2026')
        ->and(tgl(null))->toBe('-');
});
