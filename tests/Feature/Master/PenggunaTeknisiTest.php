<?php

use App\Enums\BagianTeknisi;
use App\Enums\Role;
use App\Livewire\Master\PenggunaIndex;
use App\Models\Cabang;
use App\Models\Teknisi;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('supabase');
    config(['filesystems.default' => 'supabase']);

    $this->cabang = Cabang::factory()->create();
    $this->admin = User::factory()->adminCabang($this->cabang)->create();
});

function profilTeknisi(User $user): ?Teknisi
{
    return $user->teknisi()->withoutGlobalScopes()->withTrashed()->first();
}

test('membuat pengguna role teknisi sekaligus membuat profil teknisi', function () {
    Livewire::actingAs($this->admin)->test(PenggunaIndex::class)
        ->call('tambah')
        ->assertSet('role', Role::Teknisi->value)
        ->set('nama', 'Joko')
        ->set('username', 'joko')
        ->set('password', 'rahasia123')
        ->set('bagian', BagianTeknisi::Bengkel->value)
        ->set('hp', '0812-3456-7890')
        ->set('tgl_masuk', '2026-01-05')
        ->set('foto', UploadedFile::fake()->image('joko.jpg'))
        ->call('simpan')
        ->assertHasNoErrors();

    $user = User::where('username', 'joko')->first();
    $teknisi = profilTeknisi($user);

    expect($teknisi)->not->toBeNull()
        ->and($teknisi->nama)->toBe('Joko')
        ->and($teknisi->cabang_id)->toBe($this->cabang->id)
        ->and($teknisi->bagian)->toBe(BagianTeknisi::Bengkel)
        ->and($teknisi->hp)->toBe('081234567890')
        ->and(tgl($teknisi->tgl_masuk))->toBe('05/01/2026')
        ->and($teknisi->foto)->toStartWith('teknisi/');

    Storage::disk('supabase')->assertExists($teknisi->foto);
});

test('bagian wajib diisi untuk teknisi', function () {
    Livewire::actingAs($this->admin)->test(PenggunaIndex::class)
        ->call('tambah')
        ->set('nama', 'X')->set('username', 'xx')->set('password', 'rahasia123')
        ->set('bagian', '')
        ->call('simpan')
        ->assertHasErrors('bagian');
});

test('role selain teknisi tidak membuat profil teknisi', function () {
    $owner = User::factory()->owner()->create();

    Livewire::actingAs($owner)->test(PenggunaIndex::class)
        ->call('tambah')
        ->set('role', Role::AdminCabang->value)
        ->set('cabang_id', $this->cabang->id)
        ->set('nama', 'Admin Baru')->set('username', 'adminbaru')->set('password', 'rahasia123')
        ->call('simpan')
        ->assertHasNoErrors();

    expect(Teknisi::withoutGlobalScopes()->count())->toBe(0);
});

test('mengubah pengguna teknisi memperbarui profil, foto lama diganti', function () {
    $user = User::factory()->teknisi($this->cabang, ['foto' => 'teknisi/lama.jpg'])->create();
    Storage::disk('supabase')->put('teknisi/lama.jpg', 'x');

    Livewire::actingAs($this->admin)->test(PenggunaIndex::class)
        ->call('ubah', $user->id)
        ->assertSet('fotoLama', 'teknisi/lama.jpg')
        ->set('nama', 'Nama Diganti')
        ->set('bagian', BagianTeknisi::Keduanya->value)
        ->set('foto', UploadedFile::fake()->image('baru.jpg'))
        ->call('simpan')
        ->assertHasNoErrors();

    $teknisi = profilTeknisi($user);
    expect($teknisi->nama)->toBe('Nama Diganti')
        ->and($teknisi->bagian)->toBe(BagianTeknisi::Keduanya)
        ->and($teknisi->foto)->not->toBe('teknisi/lama.jpg');

    Storage::disk('supabase')->assertMissing('teknisi/lama.jpg');
    Storage::disk('supabase')->assertExists($teknisi->foto);
});

test('nonaktif dan hapus pengguna ikut menonaktifkan dan menghapus profil teknisi', function () {
    $user = User::factory()->teknisi($this->cabang)->create();
    $komponen = Livewire::actingAs($this->admin)->test(PenggunaIndex::class);

    $komponen->call('toggleAktif', $user->id);
    expect(profilTeknisi($user)->aktif)->toBeFalse();

    $komponen->call('hapus', $user->id);
    expect(profilTeknisi($user)->trashed())->toBeTrue()
        ->and(profilTeknisi($user)->user_id)->toBe($user->id);
});

test('owner memindah cabang teknisi ikut memindah profilnya', function () {
    $owner = User::factory()->owner()->create();
    $user = User::factory()->teknisi($this->cabang)->create();
    $cabangBaru = Cabang::factory()->create();

    Livewire::actingAs($owner)->test(PenggunaIndex::class)
        ->call('ubah', $user->id)
        ->set('cabang_id', $cabangBaru->id)
        ->call('simpan')
        ->assertHasNoErrors();

    expect(profilTeknisi($user)->cabang_id)->toBe($cabangBaru->id);
});

test('teknisi yang dijadikan admin: profil di-soft-delete, kembali jadi teknisi: profil dipulihkan', function () {
    $owner = User::factory()->owner()->create();
    $user = User::factory()->teknisi($this->cabang, ['bagian' => BagianTeknisi::Bengkel])->create();
    $idProfil = profilTeknisi($user)->id;

    Livewire::actingAs($owner)->test(PenggunaIndex::class)
        ->call('ubah', $user->id)->set('role', Role::AdminCabang->value)->call('simpan')->assertHasNoErrors();

    expect(profilTeknisi($user)->trashed())->toBeTrue();

    Livewire::actingAs($owner)->test(PenggunaIndex::class)
        ->call('ubah', $user->id)
        ->assertSet('bagian', BagianTeknisi::Bengkel->value)
        ->set('role', Role::Teknisi->value)
        ->call('simpan')
        ->assertHasNoErrors();

    $profil = profilTeknisi($user);
    expect($profil->trashed())->toBeFalse()->and($profil->id)->toBe($idProfil);
});

test('filter role di daftar pengguna', function () {
    User::factory()->teknisi($this->cabang)->create(['nama' => 'Si Teknisi']);

    Livewire::actingAs($this->admin)->test(PenggunaIndex::class)
        ->set('filterRole', Role::Teknisi->value)
        ->assertSee('Si Teknisi')
        ->assertDontSee($this->admin->nama);
});
