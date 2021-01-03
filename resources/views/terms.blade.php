<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Terms') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                @switch(\Illuminate\Support\Facades\App::getLocale())
                    @case('fr')
                        @include('partials.terms-of-service-fr')
                        @break

                    @default
                        @include('partials.terms-of-service')
                @endswitch
            </div>
        </div>
    </div>
</x-app-layout>
