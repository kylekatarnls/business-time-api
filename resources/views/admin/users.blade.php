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
                    @php
                        $lineClass = $user->apiAuthorizations->count() > 0 ? '' : 'opacity-50';
                    @endphp
                    <div class="flex flex-row w-full">
                        <a
                            href="{{ route('user-dashboard', $user->id) }}"
                            class="{{ $lineClass }} flex flex-grow flex-row hover:bg-blue-200 p-1 border-b border-gray-400"
                        >
                            <span class="flex w-1/4">
                                @php
                                    $plan = $user->getPlanId();
                                    $percentage = 100 * $user->getUnverifiedPlanRatio($plan);
                                @endphp

                                <span class="flex w-1/3">
                                    {{ $user->id }}
                                </span>
                                <span class="flex w-1/3 flex-col" style="position: relative; left: -5px;">
                                    <span>{{ round($percentage) }}%</span>
                                    <span
                                        style="border: 1px solid silver;"
                                    ><span
                                            class="block" style="height: 2px; background: #3949ab; width: {{ min(100, $percentage) }}%"
                                        ></span></span>
                                </span>

                                <span class="flex w-1/3">
                                    @if ($plan)
                                        <strong>⭐ {{ $plan }}</strong>
                                    @elseif ($user->hasVerifiedProperties())
                                        ✔️
                                    @else
                                        ❌
                                    @endif
                                </span>
                            </span>
                            <span class="flex w-1/2">
                                {{ $user->email }}
                            </span>
                            <span class=" flex w-1/4">
                                {{ $user->name }}
                            </span>
                        </a>
                        <a
                            href="{{ route('admin-user', $user->id) }}"
                            class="{{ $lineClass }} flex flex-shrink hover:bg-blue-200 p-1 border-b border-gray-400"
                        >
                            {{ __('Login') }}
                        </a>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
