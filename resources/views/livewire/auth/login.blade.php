<?php

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.auth')] #[Title('Masuk')] class extends Component {
    #[Validate('required|string|max:255', as: 'username / email')]
    public string $login = '';

    #[Validate('required|string', as: 'password')]
    public string $password = '';

    public bool $remember = false;

    public function mount(): void
    {
        if ($pesan = session('errors')?->first('login')) {
            $this->addError('login', $pesan);
        }
    }

    /** Login dengan username atau email. Akun nonaktif / terhapus tidak bisa masuk. */
    public function masuk(): void
    {
        $this->validate();

        $this->ensureIsNotRateLimited();

        $kolom = filter_var($this->login, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        $kredensial = [$kolom => Str::lower(trim($this->login)), 'password' => $this->password];

        if (! Auth::validate($kredensial)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        $user = User::where($kolom, $kredensial[$kolom])->first();

        if (! $user->aktif) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['login' => 'Akun Anda tidak aktif. Hubungi admin.']);
        }

        Auth::login($user, $this->remember);

        RateLimiter::clear($this->throttleKey());
        Session::regenerate();

        $tujuan = $user->isTeknisi() ? route('teknisi.beranda', absolute: false) : route('dashboard', absolute: false);

        $this->redirectIntended(default: $tujuan, navigate: true);
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'login' => __('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->login).'|'.request()->ip());
    }
}; ?>

<div class="flex flex-col gap-6">
    <x-auth-header title="Masuk ke akun Anda" description="Gunakan username atau email dan password Anda" />

    <form wire:submit="masuk" class="flex flex-col gap-6">
        <flux:input wire:model="login" label="Username atau email" type="text" name="login" required autofocus autocomplete="username" autocapitalize="none" />

        <flux:input wire:model="password" label="Password" type="password" name="password" required autocomplete="current-password" viewable />

        <flux:checkbox wire:model="remember" label="Ingat saya" />

        <flux:button variant="primary" type="submit" class="w-full">Masuk</flux:button>
    </form>

    <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">Lupa password? Hubungi admin cabang Anda.</p>
</div>
