<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('halaman login bisa dibuka', function () {
    $this->get('/login')->assertOk()->assertSee('Masuk');
});

test('tamu diarahkan ke login', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/')->assertRedirect('/login');
});

test('admin bisa login dengan username dan diarahkan ke dashboard', function () {
    $user = User::factory()->adminCabang()->create(['username' => 'admin.sby']);

    Volt::test('auth.login')
        ->set('login', 'ADMIN.SBY')
        ->set('password', 'password')
        ->call('masuk')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('user bisa login dengan email', function () {
    $user = User::factory()->owner()->create(['email' => 'owner@contoh.id']);

    Volt::test('auth.login')
        ->set('login', 'owner@contoh.id')
        ->set('password', 'password')
        ->call('masuk')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

test('teknisi diarahkan ke halaman teknisi setelah login', function () {
    $user = User::factory()->teknisi()->create();

    Volt::test('auth.login')
        ->set('login', $user->username)
        ->set('password', 'password')
        ->call('masuk')
        ->assertRedirect(route('teknisi.beranda', absolute: false));
});

test('password salah ditolak', function () {
    $user = User::factory()->create();

    Volt::test('auth.login')
        ->set('login', $user->username)
        ->set('password', 'salah')
        ->call('masuk')
        ->assertHasErrors('login');

    $this->assertGuest();
});

test('user nonaktif tidak bisa login', function () {
    $user = User::factory()->nonaktif()->create();

    Volt::test('auth.login')
        ->set('login', $user->username)
        ->set('password', 'password')
        ->call('masuk')
        ->assertHasErrors('login');

    $this->assertGuest();
});

test('user yang sudah dihapus tidak bisa login', function () {
    $user = User::factory()->create();
    $user->delete();

    Volt::test('auth.login')
        ->set('login', $user->username)
        ->set('password', 'password')
        ->call('masuk')
        ->assertHasErrors('login');
});

test('user yang dinonaktifkan saat masih login langsung dikeluarkan', function () {
    $user = User::factory()->adminCabang()->create();
    $this->actingAs($user);
    $user->update(['aktif' => false]);

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('user bisa logout', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});

test('halaman registrasi tidak tersedia', function () {
    $this->get('/register')->assertNotFound();
});
