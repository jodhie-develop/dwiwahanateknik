<?php

namespace App\Livewire\Master;

use App\Enums\BagianTeknisi;
use App\Enums\Role;
use App\Livewire\Concerns\KelolaMaster;
use App\Models\Cabang;
use App\Models\Teknisi;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Support\KonteksCabang;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Kelola akun pengguna sekaligus profil teknisi.
 * User ber-role teknisi selalu punya 1 baris di tabel teknisi (dipakai tim, order, komisi).
 */
#[Title('Pengguna')]
class PenggunaIndex extends Component
{
    use KelolaMaster, WithFileUploads;

    #[Url(as: 'role', except: '')]
    public string $filterRole = '';

    // --- Akun ---
    public string $nama = '';

    public string $username = '';

    public string $email = '';

    public string $role = '';

    public ?int $cabang_id = null;

    public string $password = '';

    public bool $aktif = true;

    // --- Profil teknisi (hanya jika role = teknisi) ---
    public string $bagian = 'lapangan';

    public string $hp = '';

    public ?string $tgl_masuk = null;

    /** @var TemporaryUploadedFile|null */
    public $foto = null;

    public ?string $fotoLama = null;

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
    }

    public function updatingFilterRole(): void
    {
        $this->resetPage();
    }

    protected function modelClass(): string
    {
        return User::class;
    }

    protected function kolomCari(): array
    {
        return ['users.nama', 'users.username', 'users.email'];
    }

    /** User tidak memakai CabangScope, jadi batasi manual di sini. */
    protected function cariModel(int $id): Model
    {
        return User::query()->terlihatOleh(auth()->user())->findOrFail($id);
    }

    protected function isiForm(Model $model): void
    {
        $this->nama = $model->nama;
        $this->username = $model->username;
        $this->email = (string) $model->email;
        $this->role = $model->role->value;
        $this->cabang_id = $model->cabang_id;
        $this->aktif = $model->aktif;
        $this->password = '';
        $this->foto = null;

        $teknisi = $model->teknisi()->withoutGlobalScopes()->withTrashed()->first();
        $this->bagian = $teknisi?->bagian->value ?? BagianTeknisi::Lapangan->value;
        $this->hp = (string) $teknisi?->hp;
        $this->tgl_masuk = $teknisi?->tgl_masuk?->format('Y-m-d');
        $this->fotoLama = $teknisi?->foto;
    }

    protected function kosongkanForm(): void
    {
        $this->reset('nama', 'username', 'email', 'password', 'aktif', 'bagian', 'hp', 'tgl_masuk', 'foto', 'fotoLama');
        $this->role = Role::Teknisi->value;
        $this->cabang_id = auth()->user()->isOwner()
            ? (KonteksCabang::filterOwner() ?? Cabang::query()->aktif()->value('id'))
            : auth()->user()->cabang_id;
    }

    public function simpan(): void
    {
        $saya = auth()->user();
        $this->username = Str::lower(trim($this->username));
        $this->email = Str::lower(trim($this->email));
        $this->hp = preg_replace('/[^0-9+]/', '', $this->hp);

        $roleBoleh = array_map(fn (Role $r) => $r->value, UserPolicy::roleYangBolehDiberikan($saya));
        $teknisi = $this->role === Role::Teknisi->value;

        $data = $this->validate([
            'nama' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($this->editId)],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editId)],
            'role' => ['required', Rule::in($roleBoleh)],
            'cabang_id' => [
                Rule::requiredIf(fn () => $this->role !== Role::Owner->value),
                'nullable', 'integer', Rule::exists('cabang', 'id')->whereNull('deleted_at'),
            ],
            'password' => [$this->editId ? 'nullable' : 'required', 'string', Password::min(8)],
            'aktif' => ['boolean'],
            'bagian' => [Rule::requiredIf($teknisi), 'nullable', Rule::enum(BagianTeknisi::class)],
            'hp' => ['nullable', 'string', 'max:30'],
            'tgl_masuk' => ['nullable', 'date'],
            'foto' => ['nullable', 'image', 'max:4096'],
        ], [
            'username.regex' => 'Username hanya boleh huruf kecil, angka, titik, strip, dan garis bawah.',
        ], [
            'cabang_id' => 'cabang',
            'tgl_masuk' => 'tanggal masuk',
            'hp' => 'nomor HP',
        ]);

        $akun = collect($data)->only(['nama', 'username', 'email', 'role', 'cabang_id', 'password', 'aktif'])->all();

        // Owner tidak terikat cabang; admin cabang selalu membuat user di cabangnya sendiri.
        if ($akun['role'] === Role::Owner->value) {
            $akun['cabang_id'] = null;
        } elseif (! $saya->isOwner()) {
            $akun['cabang_id'] = $saya->cabang_id;
        }

        $akun['email'] = $akun['email'] ?: null;

        if (blank($akun['password'])) {
            unset($akun['password']);
        }

        if ($this->editId) {
            $user = $this->cariModel($this->editId);
            $this->authorize('update', $user);

            if ($user->is($saya) && (! $akun['aktif'] || $akun['role'] !== $saya->role->value)) {
                $this->addError('role', 'Anda tidak bisa mengubah role atau menonaktifkan akun sendiri.');

                return;
            }
        } else {
            $this->authorize('create', User::class);
            $user = new User;
        }

        $fotoBaru = $teknisi && $this->foto ? $this->foto->store('teknisi') : null;

        DB::transaction(function () use ($user, $akun, $teknisi, $data, $fotoBaru) {
            $user->fill($akun)->save();

            $profil = $user->teknisi()->withoutGlobalScopes()->withTrashed()->first();

            if (! $teknisi) {
                // Role berubah dari teknisi: profil dinonaktifkan (soft delete) agar riwayat tim/order tetap ada.
                $profil?->delete();

                return;
            }

            $profil ??= new Teknisi(['user_id' => $user->id]);

            if ($fotoBaru && $profil->foto) {
                Storage::delete($profil->foto);
            }

            $profil->forceFill([
                'cabang_id' => $user->cabang_id,
                'nama' => $user->nama,
                'aktif' => $user->aktif,
                'bagian' => $data['bagian'],
                'hp' => $data['hp'] ?: null,
                'tgl_masuk' => $data['tgl_masuk'] ?: null,
                'foto' => $fotoBaru ?? $profil->foto,
                'deleted_at' => null,
            ])->save();
        });

        $this->selesaiSimpan();
    }

    public function toggleAktif(int $id): void
    {
        abort_if($id === auth()->id(), 403, 'Tidak bisa menonaktifkan akun sendiri.');

        $user = $this->cariModel($id);
        $this->authorize('update', $user);
        $user->update(['aktif' => ! $user->aktif]);
    }

    public function render()
    {
        $saya = auth()->user();
        $filterCabang = KonteksCabang::idPembatas();

        $daftar = $this->terapkanFilter(User::query()->terlihatOleh($saya))
            ->when($saya->isOwner() && $filterCabang, fn ($q) => $q->where(fn ($q) => $q
                ->where('cabang_id', $filterCabang)->orWhereNull('cabang_id')))
            ->when($this->filterRole !== '', fn ($q) => $q->where('role', $this->filterRole))
            ->with(['cabang:id,nama', 'teknisi' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'user_id', 'bagian', 'hp', 'foto')])
            ->orderBy('role')
            ->orderBy('nama')
            ->paginate(15);

        return view('livewire.master.pengguna-index', [
            'daftar' => $daftar,
            'pilihanRole' => UserPolicy::roleYangBolehDiberikan($saya),
            'pilihanCabang' => Cabang::query()->aktif()->orderBy('nama')->get(['id', 'nama']),
            'pilihanBagian' => BagianTeknisi::options(),
        ]);
    }
}
