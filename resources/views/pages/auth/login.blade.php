<x-layouts::auth title="Masuk">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Masuk ke Tahu Go" description="Masukkan username dan password Anda" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6">
            @csrf

            <!-- Username -->
            <flux:input
                name="username"
                label="Username"
                :value="old('username')"
                type="text"
                required
                autofocus
                autocapitalize="none"
                autocomplete="username"
                class:input="text-base"
            />

            <!-- Password -->
            <flux:input
                name="password"
                label="Password"
                type="password"
                required
                autocomplete="current-password"
                class:input="text-base"
                viewable
            />

            <!-- Remember Me -->
            <flux:checkbox name="remember" label="Ingat saya" :checked="old('remember')" />

            <flux:button variant="primary" type="submit" class="h-12 w-full text-base" data-test="login-button">
                Masuk
            </flux:button>

            <flux:text class="text-center">Lupa password? Hubungi pemilik untuk reset.</flux:text>
        </form>
    </div>
</x-layouts::auth>
