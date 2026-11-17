<?php

namespace App\Support;

use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Kirim notifikasi tanpa menggagalkan aksi utama.
 * Push notification bergantung pada layanan luar (FCM/Apple); jika gagal, cukup dicatat di log.
 */
class KirimNotifikasi
{
    public static function aman(mixed $penerima, BaseNotification $notifikasi): bool
    {
        try {
            Notification::send($penerima, $notifikasi);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
