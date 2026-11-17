@props(['aktif'])

@if ($aktif)
    <flux:badge size="sm" color="green" inset="top bottom">Aktif</flux:badge>
@else
    <flux:badge size="sm" color="zinc" inset="top bottom">Nonaktif</flux:badge>
@endif
