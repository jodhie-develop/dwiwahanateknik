<flux:input wire:model="nama" label="Nama" required />
<flux:input wire:model="hp" label="No. HP / WA" type="tel" placeholder="081234567890" />
<flux:textarea wire:model="alamat" label="Alamat" rows="2" />
@include('livewire.master.partials.input-koordinat')
<flux:textarea wire:model="catatan" label="Catatan" rows="2" />
<flux:switch wire:model="aktif" label="Aktif" />
