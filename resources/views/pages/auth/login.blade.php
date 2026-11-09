<x-layouts::auth title="Masuk">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Selamat datang kembali" description="Masuk ke Tahu Go Business untuk melanjutkan operasional Anda." />

        <!-- Session Status -->
        <x-auth-session-status :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <!-- Username -->
            <flux:input
                name="username"
                label="Username *"
                :value="old('username')"
                type="text"
                icon:trailing="user"
                required
                autofocus
                autocapitalize="none"
                autocomplete="username"
                placeholder="Masukkan username"
                class:input="h-12 text-base"
            />

            <!-- Password -->
            <flux:input
                name="password"
                label="Kata sandi *"
                type="password"
                required
                autocomplete="current-password"
                placeholder="Masukkan kata sandi"
                class:input="h-12 text-base"
                viewable
            />

            <!-- Remember Me -->
            <flux:checkbox name="remember" label="Ingat saya" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" icon="arrow-right" class="h-12 w-full text-base" data-test="login-button">
                Masuk
            </flux:button>
        </form>

        <div class="flex flex-col gap-1 border-t border-border pt-5 text-center text-sm">
            <span class="text-muted">Belum memiliki akses atau lupa kata sandi?</span>
            <span class="font-medium text-brown">Hubungi pemilik untuk mendapatkan akun.</span>
        </div>
    </div>
</x-layouts::auth>
