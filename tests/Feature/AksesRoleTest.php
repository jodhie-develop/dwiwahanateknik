<?php

use App\Models\User;

dataset('halaman_admin', [
    'dashboard' => 'dashboard',
    'pengguna' => 'master.pengguna',
    'tim' => 'master.tim',
    'jenis perangkat' => 'master.jenis-perangkat',
    'layanan' => 'master.layanan',
    'pelanggan' => 'master.pelanggan',
    'barang' => 'master.barang',
]);

test('owner bisa membuka semua halaman admin', function (string $route) {
    $this->actingAs(User::factory()->owner()->create())->get(route($route))->assertOk();
})->with('halaman_admin');

test('admin cabang bisa membuka halaman admin', function (string $route) {
    $this->actingAs(User::factory()->adminCabang()->create())->get(route($route))->assertOk();
})->with('halaman_admin');

test('teknisi tidak bisa membuka halaman admin', function (string $route) {
    $this->actingAs(User::factory()->teknisi()->create())->get(route($route))->assertForbidden();
})->with('halaman_admin');

test('teknisi bisa membuka halaman teknisi', function () {
    $this->actingAs(User::factory()->teknisi()->create())->get(route('teknisi.beranda'))->assertOk();
});

test('admin tidak bisa membuka halaman teknisi', function () {
    $this->actingAs(User::factory()->adminCabang()->create())->get(route('teknisi.beranda'))->assertForbidden();
});

test('halaman beranda mengarahkan sesuai role', function () {
    $this->actingAs(User::factory()->teknisi()->create())->get('/')->assertRedirect(route('teknisi.beranda'));
    $this->actingAs(User::factory()->owner()->create())->get('/')->assertRedirect(route('dashboard'));
});

test('user yang sudah login lalu membuka /login diarahkan sesuai role', function () {
    $this->actingAs(User::factory()->teknisi()->create())->get('/login')->assertRedirect(route('home'));
    $this->get(route('home'))->assertRedirect(route('teknisi.beranda'));
});

test('semua role bisa membuka pengaturan akun', function () {
    foreach ([User::factory()->owner()->create(), User::factory()->teknisi()->create()] as $user) {
        $this->actingAs($user)->get(route('settings.profile'))->assertOk();
        $this->actingAs($user)->get(route('settings.password'))->assertOk();
    }
});
