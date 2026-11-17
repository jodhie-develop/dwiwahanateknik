<?php

use App\Models\Cabang;
use App\Models\User;

beforeEach(function () {
    $this->cabang = Cabang::factory()->create();
    $this->teknisi = sebagaiSistem(fn () => User::factory()->teknisi($this->cabang)->create());
});

test('teknisi bisa membuka halaman aplikasi & notifikasi', function () {
    $this->actingAs($this->teknisi)->get(route('teknisi.perangkat'))
        ->assertOk()
        ->assertSee('Pasang aplikasi di HP')
        ->assertSee('Pasang ke perangkat')
        ->assertSee('Aktifkan notifikasi')
        ->assertSee('Matikan notifikasi')
        ->assertSee('name="csrf-token"', false);
});

test('menu & beranda teknisi menautkan halaman aplikasi & notifikasi', function () {
    $this->actingAs($this->teknisi)->get(route('teknisi.beranda'))
        ->assertOk()
        ->assertSee(route('teknisi.perangkat'), false)
        ->assertSee('Aplikasi &amp; Notifikasi', false);
});

test('admin tidak bisa membuka halaman perangkat teknisi', function () {
    $admin = User::factory()->adminCabang($this->cabang)->create();

    $this->actingAs($admin)->get(route('teknisi.perangkat'))->assertForbidden();
});

test('teknisi bisa mematikan notifikasi (langganan dihapus)', function () {
    $this->actingAs($this->teknisi)
        ->postJson(route('teknisi.push.simpan'), [
            'endpoint' => 'https://push.contoh.test/abc',
            'keys' => ['p256dh' => 'kunci', 'auth' => 'rahasia'],
        ])->assertOk();
    expect($this->teknisi->pushSubscriptions()->count())->toBe(1);

    $this->deleteJson(route('teknisi.push.hapus'), ['endpoint' => 'https://push.contoh.test/abc'])->assertOk();
    expect($this->teknisi->pushSubscriptions()->count())->toBe(0);
});
