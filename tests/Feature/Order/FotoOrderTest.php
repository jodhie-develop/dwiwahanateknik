<?php

use App\Livewire\Bengkel\TerimaBarang;
use App\Livewire\Order\FotoOrder;
use App\Models\Cabang;
use App\Models\OrderServis;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake();
    $this->cabang = Cabang::factory()->create();
    $this->admin = User::factory()->adminCabang($this->cabang)->create();
    $this->actingAs($this->admin);
    $this->order = OrderServis::factory()->create(['cabang_id' => $this->cabang->id]);
});

test('setiap foto yang diunggah langsung disimpan ke order sesuai kategori', function () {
    $c = Livewire::test(FotoOrder::class, ['orderId' => $this->order->id])
        ->set('kategori', 'sesudah')
        ->set('berkas', UploadedFile::fake()->image('a.jpg'))
        ->assertHasNoErrors()
        ->assertSet('berkas', null)
        ->set('berkas', UploadedFile::fake()->image('b.jpg'))
        ->assertHasNoErrors();

    $foto = $this->order->foto()->get();
    expect($foto)->toHaveCount(2)
        ->and($foto->pluck('kategori')->map->value->unique()->all())->toBe(['sesudah']);
    $foto->each(fn ($f) => Storage::assertExists($f->path));
    $c->assertSee('Sesudah');
});

test('file bukan gambar ditolak dan tidak disimpan', function () {
    Livewire::test(FotoOrder::class, ['orderId' => $this->order->id])
        ->set('berkas', UploadedFile::fake()->create('dok.pdf', 10, 'application/pdf'))
        ->assertHasErrors('berkas');

    expect($this->order->foto()->count())->toBe(0);
});

test('input foto tidak memakai upload multiple Livewire (tidak didukung S3)', function () {
    Livewire::test(FotoOrder::class, ['orderId' => $this->order->id])
        ->assertDontSeeHtml('wire:model="berkas"')
        ->assertSeeHtml('$wire.upload(');

    Livewire::test(TerimaBarang::class)
        ->assertDontSeeHtml('wire:model="foto"')
        ->assertSeeHtml('$wire.upload(');
});

test('foto penerimaan bengkel dikumpulkan satu per satu, maksimal 10', function () {
    $c = Livewire::test(TerimaBarang::class);

    foreach (range(1, 11) as $i) {
        $c->set('fotoBaru', UploadedFile::fake()->image("f{$i}.jpg"));
    }

    $c->assertSet('fotoBaru', null)->assertHasErrors('fotoBaru');
    expect($c->get('foto'))->toHaveCount(10);

    $c->call('hapusFoto', 0);
    expect($c->get('foto'))->toHaveCount(9);
});
