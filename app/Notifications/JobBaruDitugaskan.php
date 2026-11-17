<?php

namespace App\Notifications;

use App\Models\OrderServis;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

/** Push notification ke HP teknisi saat timnya mendapat job baru. */
class JobBaruDitugaskan extends Notification
{
    /** @param  list<int>  $orderIds */
    public function __construct(public array $orderIds) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        $jumlah = count($this->orderIds);

        if ($jumlah === 1) {
            $order = OrderServis::query()->withoutGlobalScopes()->with('pelanggan:id,nama')->find($this->orderIds[0]);
            $judul = 'Job baru: '.($order?->pelanggan?->nama ?? 'pelanggan');
            $isi = $order ? $order->no_order.' · '.tgl($order->tgl_jadwal) : 'Buka aplikasi untuk detail.';
            $url = $order ? route('teknisi.job', $order, absolute: false) : route('teknisi.beranda', absolute: false);
        } else {
            $judul = "{$jumlah} job baru untuk tim Anda";
            $isi = 'Buka aplikasi untuk melihat antrian.';
            $url = route('teknisi.beranda', absolute: false);
        }

        return (new WebPushMessage)
            ->title($judul)
            ->body($isi)
            ->icon('/icons/icon-192.png')
            ->badge('/icons/badge-72.png')
            ->tag('job-baru')
            ->renotify()
            ->data(['url' => $url]);
    }
}
