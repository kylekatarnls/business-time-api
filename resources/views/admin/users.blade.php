<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Users') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                @foreach($users as $user)
                    <a class="flex flex-row w-full hover:bg-blue-200 p-1 border-b border-gray-400" href="{{ route('admin-user', $user->id) }}">
                        <span class="flex w-1/4">
                            {{ $user->id }}
                        </span>
                        <span class="flex w-1/2">
                            {{ $user->email }}
                        </span>
                        <span class="flex w-1/4">
                            {{ $user->name }}
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
