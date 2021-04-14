<x-guest-layout>
    <x-jet-authentication-card>
        <x-slot name="logo">
            <x-jet-authentication-card-logo />
        </x-slot>

        <x-jet-validation-errors class="mb-4" />

        @if (session('status'))
            <div class="mb-4 font-medium text-sm text-green-600">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}">
            @csrf

            <div>
                <x-jet-label for="email" value="{{ __('Email') }}" />
                <x-jet-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus />
            </div>

            <div class="mt-4">
                <x-jet-label for="password" value="{{ __('Password') }}" />
                <x-jet-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
            </div>

            <div class="block mt-4">
                <label for="remember_me" class="flex items-center">
                    <input id="remember_me" type="checkbox" class="form-checkbox" name="remember">
                    <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                </label>
            </div>

            <div class="flex items-center justify-end mt-4">
                <div>
                    @if (Route::has('password.request'))
                        <div>
                            <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('password.request') }}">
                                {{ __('Forgot your password?') }}
                            </a>
                        </div>
                    @endif

                    @if (Route::has('register'))
                        <div>
                            <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('register') }}">
                                {{ __('Not yet registered?') }}
                            </a>
                        </div>
                    @endif
                </div>

                <x-jet-button class="ml-4">
                    {{ __('Login') }}
                </x-jet-button>
            </div>

            @if (empty($_COOKIE['reg']) && now()->isBefore('2021-06-01'))
                <p style="font-size: 0.9em; margin-top: 1em;">
                    Suite à un incendit ayant détruit notre datacenter, les comptes gratuits créés entre janvier et mars
                    ont été perdu, si vous vous étiez déjà inscrit, nous vous prions de bien vouloir recréer votre compte
                    et vous prions de nous excuser pour la gène occasionnée.
                </p>
            @endif
        </form>
    </x-jet-authentication-card>
</x-guest-layout>
