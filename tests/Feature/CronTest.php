<?php

use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['services.cron.secret' => 'rahasia-uji', 'livewire.temporary_file_upload.disk' => 'tmpuji']);
    config(['filesystems.disks.tmpuji' => ['driver' => 'local', 'root' => storage_path('framework/testing/tmpuji')]]);
    Storage::fake('tmpuji');
});

test('cron menolak tanpa / dengan secret salah', function () {
    $this->get('/cron/bersihkan-upload-sementara')->assertUnauthorized();
    $this->withHeader('Authorization', 'Bearer salah')->get('/cron/bersihkan-upload-sementara')->assertUnauthorized();
});

test('cron ditolak jika CRON_SECRET belum diatur', function () {
    config(['services.cron.secret' => null]);
    $this->withHeader('Authorization', 'Bearer ')->get('/cron/bersihkan-upload-sementara')->assertUnauthorized();
});

test('cron menghapus upload sementara yang lebih tua dari 24 jam saja', function () {
    $disk = Storage::disk('tmpuji');
    $disk->put('livewire-tmp/lama.jpg', 'x');
    $disk->put('livewire-tmp/baru.jpg', 'x');
    touch($disk->path('livewire-tmp/lama.jpg'), now()->subDays(2)->getTimestamp());

    $this->withHeader('Authorization', 'Bearer rahasia-uji')
        ->get('/cron/bersihkan-upload-sementara')
        ->assertOk()
        ->assertJson(['dihapus' => 1]);

    $disk->assertMissing('livewire-tmp/lama.jpg');
    $disk->assertExists('livewire-tmp/baru.jpg');
});

test('URL memakai https bila di belakang proxy Vercel', function () {
    $this->get('/login', ['X-Forwarded-Proto' => 'https', 'X-Forwarded-Host' => 'contoh.vercel.app'])
        ->assertOk()
        ->assertSee('https://contoh.vercel.app/livewire', false);
});
