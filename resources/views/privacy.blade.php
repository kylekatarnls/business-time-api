<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Privacy') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                @switch(\Illuminate\Support\Facades\App::getLocale())
                    @case('fr')
                        @include('partials.privacy-fr')
                        @break

                    @default
                        @include('partials.privacy')
                @endswitch
            </div>
        </div>
    </div>
</x-app-layout>
