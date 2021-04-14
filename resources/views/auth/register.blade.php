<x-guest-layout>
    <x-jet-authentication-card>
        <x-slot name="logo">
            <x-jet-authentication-card-logo />
        </x-slot>

        <x-jet-validation-errors class="mb-4" />

        <form method="POST" action="{{ route('register') }}">
            @csrf

            <div>
                <x-jet-label for="name" value="{{ __('Name') }}" />
                <x-jet-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            </div>

            <div class="mt-4">
                <x-jet-label for="email" value="{{ __('Email') }}" />
                <x-jet-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required />
            </div>

            <div class="mt-4">
                <x-jet-label for="password" value="{{ __('Password') }}" />
                <x-jet-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="mt-4">
                <x-jet-label for="password_confirmation" value="{{ __('Confirm Password') }}" />
                <x-jet-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            <div class="mt-4">
                <x-jet-input type="checkbox" required class="big-checkbox" />
                {!! __('I accept :the-terms-of-service', [
                    'the-terms-of-service' => '<a href="' . route('terms') . '" target="_blank" class="underline">' .
                        __('Terms of Service') . '</a>',
                ]) !!}
            </div>

            <div class="flex items-center justify-end mt-4">
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                    {{ __('Already registered?') }}
                </a>

                <x-jet-button class="ml-4">
                    {{ __('Register') }}
                </x-jet-button>
            </div>

            <div class="flex items-center text-center mt-8">
                <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('privacy') }}" target="_blank">
                    {{ __('Privacy') }}
                </a>
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
