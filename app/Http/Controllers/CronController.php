<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Tugas terjadwal yang dipanggil Vercel Cron (lihat vercel.json).
 * Dilindungi CRON_SECRET: Vercel mengirim header "Authorization: Bearer <CRON_SECRET>".
 */
class CronController extends Controller
{
    /**
     * Hapus file upload sementara Livewire (folder livewire-tmp di bucket) yang lebih tua dari 24 jam.
     * Di disk lokal Livewire membersihkannya sendiri; di bucket S3/Supabase tidak.
     */
    public function bersihkanUploadSementara(Request $request): JsonResponse
    {
        $rahasia = (string) config('services.cron.secret');
        abort_if($rahasia === '' || ! hash_equals('Bearer '.$rahasia, (string) $request->header('Authorization')), 401);

        $disk = Storage::disk(config('livewire.temporary_file_upload.disk') ?: config('filesystems.default'));
        $batas = now()->subDay()->getTimestamp();
        $dihapus = 0;

        foreach ($disk->files('livewire-tmp') as $path) {
            try {
                if ($disk->lastModified($path) < $batas) {
                    $disk->delete($path);
                    $dihapus++;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return response()->json(['dihapus' => $dihapus]);
    }
}
