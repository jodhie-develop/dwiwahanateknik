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
        $this->pastikanCron($request);

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

    /**
     * SEMENTARA — diagnosa konfigurasi penyimpanan di Vercel. Hanya melaporkan ada/tidaknya variabel
     * (tanpa nilai rahasia) dan mencoba langkah awal upload Livewire. Hapus setelah masalah upload beres.
     */
    public function diagnosaPenyimpanan(Request $request): JsonResponse
    {
        $this->pastikanCron($request);

        $ada = fn (string $k) => filled(env($k));
        $hasil = [
            'filesystems.default' => config('filesystems.default'),
            'livewire.tmp_disk' => config('livewire.temporary_file_upload.disk'),
            'env_terisi' => collect(['FILESYSTEM_DISK', 'LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK', 'SUPABASE_S3_KEY', 'SUPABASE_S3_SECRET', 'SUPABASE_S3_REGION', 'SUPABASE_S3_ENDPOINT', 'SUPABASE_BUCKET'])
                ->mapWithKeys(fn ($k) => [$k => $ada($k)]),
            'endpoint_host' => parse_url((string) config('filesystems.disks.supabase.endpoint'), PHP_URL_HOST),
            'bucket' => config('filesystems.disks.supabase.bucket'),
            'ekstensi' => collect(['fileinfo', 'gd', 'curl', 'openssl'])->mapWithKeys(fn ($e) => [$e => extension_loaded($e)]),
        ];

        foreach ([
            'presigned_put' => fn () => (bool) Storage::disk('supabase')->temporaryUploadUrl('livewire-tmp/diagnosa.txt', now()->addMinutes(1)),
            'list_tmp' => fn () => count(Storage::disk('supabase')->files('livewire-tmp')),
        ] as $nama => $uji) {
            try {
                $hasil['uji'][$nama] = $uji();
            } catch (Throwable $e) {
                $hasil['uji'][$nama] = 'GAGAL: '.class_basename($e).': '.mb_substr($e->getMessage(), 0, 300);
            }
        }

        return response()->json($hasil);
    }

    private function pastikanCron(Request $request): void
    {
        $rahasia = (string) config('services.cron.secret');
        abort_if($rahasia === '' || ! hash_equals('Bearer '.$rahasia, (string) $request->header('Authorization')), 401);
    }
}
