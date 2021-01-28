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
                    <div class="flex flex-row w-full">
                        <a
                            href="{{ route('user-dashboard', $user->id) }}"
                            class="flex flex-grow flex-row hover:bg-blue-200 p-1 border-b border-gray-400"
                        >
                            <span class="flex w-1/4">
                                @php
                                    $plan = $user->getPlanId();
                                @endphp

                                <span class="flex w-1/3">
                                    {{ $user->id }}
                                </span>
                                <span class="flex w-1/3 flex-col" style="position: relative; left: -5px;">
                                    <span>{{ round(100 * $user->getPlanRatio($plan)) }}%</span>
                                    <span
                                        style="border: 1px solid silver;"
                                    ><span
                                            class="block" style="height: 2px; background: #3949ab; width: {{ 100 * min(1, $user->getPlanRatio()) }}%"
                                        ></span></span>
                                </span>

                                @if ($plan)
                                    <strong class="flex w-1/3">{{ $plan }}</strong>
                                @endif
                            </span>
                            <span class="flex w-1/2">
                                {{ $user->email }}
                            </span>
                            <span class="flex w-1/4">
                                {{ $user->name }}
                            </span>
                        </a>
                        <a
                            href="{{ route('admin-user', $user->id) }}"
                            class="flex flex-shrink hover:bg-blue-200 p-1 border-b border-gray-400"
                        >
                            {{ __('Login') }}
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
