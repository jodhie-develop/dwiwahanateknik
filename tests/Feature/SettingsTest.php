<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

test('user bisa mengubah nama dan email', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)->test('settings.profile')
        ->set('nama', 'Nama Baru')
        ->set('email', 'BARU@contoh.id')
        ->call('simpan')
        ->assertHasErrors('email'); // harus huruf kecil

    Volt::actingAs($user)->test('settings.profile')
        ->set('nama', 'Nama Baru')
        ->set('email', '')
        ->call('simpan')
        ->assertHasNoErrors();

    expect($user->fresh())->nama->toBe('Nama Baru')->email->toBeNull();
});

test('user bisa mengubah password', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)->test('settings.password')
        ->set('current_password', 'password')
        ->set('password', 'passwordbaru123')
        ->set('password_confirmation', 'passwordbaru123')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('passwordbaru123', $user->fresh()->password))->toBeTrue();
});

test('password lama harus benar', function () {
    $user = User::factory()->create();

    Volt::actingAs($user)->test('settings.password')
        ->set('current_password', 'salah')
        ->set('password', 'passwordbaru123')
        ->set('password_confirmation', 'passwordbaru123')
        ->call('updatePassword')
        ->assertHasErrors('current_password');
});
