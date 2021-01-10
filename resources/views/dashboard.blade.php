<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                @foreach($errors as $error)
                    <div class="px-6 py-4 mb-6 bg-red-300 border-red-500">
                        {{ $error }}
                    </div>
                @endforeach
                @foreach($authorizations as $authorizationsData)
                    @foreach($authorizationsData->list as $authorization)
                        @if(!$authorization->isVerified())
                            <div class="px-6 py-4 mb-6 bg-blue-300 border-blue-500">
                                {!! $authorization->getVerification() !!}
                                @if($authorization->needsManualVerification())
                                    <div class="mt-3">
                                        <a
                                            class="
                                                px-4 py-2 rounded-md
                                                bg-blue-600 hover:bg-blue-500
                                                font-semibold text-xs text-white uppercase
                                            "
                                            href="{{ route('verify-authorization', $authorization->pick(['type', 'value'])) }}"
                                        >{{ __('Verify') }}</a>
                                    </div>
                                @endif
                            </div>
                            @if($verifyError && $verifiedAuthorization === $authorization->id)
                                <div class="px-6 py-4 mb-6 bg-red-300 border-red-500">
                                    {{ $verifyError }}
                                </div>
                            @endif
                        @endif
                    @endforeach
                @endforeach

                @if($authorizationsCount)
                    @if($hasVerifiedProperties)
                        <p class="my-4">
                            {{ __('Each verified domain or IP get :requests requests per month for free.', [
                                'requests' => Number::format($freeLimit),
                            ]) }}
                        </p>
                        @if($limit === INF)
                            <p class="my-4 p-3 bg-green-100">
                                <span class="py-1 px-3 mr-2 bg-green-700 text-white text-sm rounded-lg">{{ $plan['name'] }}</span>
                                {{ __('Total paid requests: :requests', [
                                   'requests' => Number::format($paidRequests),
                                ]) }}
                            </p>
                        @elseif($limit)
                            <div class="my-4 p-3 bg-yellow-100">
                                <p>
                                    <span class="py-1 px-3 mr-2 bg-green-700 text-white text-sm rounded-lg">{{ $plan['name'] }}</span>
                                    {{ __('Total paid requests: :requests / :limit', [
                                        'requests' => Number::format($paidRequests),
                                        'limit' => Number::format($plan['limit']),
                                    ]) }}
                                </p>
                                <div class="mt-2 p-1 bg-gray-100 border-2">
                                    <div
                                        class="h-2 bg-{{ $percentage > 90 ? 'red-500' : ($percentage > 75 ? 'yellow-300' : 'blue-300') }}"
                                        style="width: {{ $percentage }}%"
                                    ></div>
                                </div>
                                @if($percentage > 75)
                                    <div class="mt-2">
                                        <a
                                            href="{{ route('plan') }}"
                                            class="
                                                px-4 py-2 rounded-md
                                                bg-blue-600 hover:bg-blue-500
                                                font-semibold text-xs text-white uppercase
                                            "
                                        >
                                            {{ __('Raise account total limit') }}
                                        </a>
                                    </div>
                                @endif
                                <p class="mt-2 text-sm">{{ __('Next counter reset: :date', ['date' => $nextCounterReset]) }}</p>
                                <p class="mt-1 text-sm">{{ __('Next billing: :date', ['date' => $nextBill]) }}</p>
                            </div>
                            <p class="my-4">
                                {{ __('Your :plan subscription allow you :requests more requests per month shared among your properties.', [
                                    'plan' => $plan['name'],
                                    'requests' => Number::format($plan['limit']),
                                ]) }}
                            </p>
                        @else
                            <a
                                href="{{ route('plan') }}"
                                class="
                                    px-4 py-2 rounded-md
                                    bg-blue-600 hover:bg-blue-500
                                    font-semibold text-xs text-white uppercase
                                "
                            >
                                {{ __('Raise account total limit') }}
                            </a>
                            <p class="my-4">
                                {{ __('You can raise the total limit for your account subscribing a plan.') }}
                            </p>
                        @endif
                    @endif

                    @foreach($authorizations as $authorizationsData)
                        @if(count($authorizationsData->list) > 0)
                            <div class="pb-6">
                                <table class="table-auto pb-6">
                                    <thead>

                                    @if($hasVerifiedProperties)
                                        <tr>
                                            <th></th>
                                            <th></th>
                                            <th colspan="3" class="border-b-2">{{ __('Requests for :month', ['month' => $month]) }}</th>
                                            <th></th>
                                        </tr>
                                    @endif

                                    <tr>
                                        <th class="px-4 py-2">{{ __('Name') }}</th>
                                        <th class="px-4 py-2">{{ $authorizationsData->name }}</th>

                                        @if($hasVerifiedProperties)
                                            <th class="px-4 py-2">{{ __('Free') }}</th>
                                            <th class="px-4 py-2">{{ __('Paid') }}*</th>
                                            <th class="px-4 py-2">{{ __('Blocked') }}</th>
                                        @endif

                                        <th class="px-4 py-2"></th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($authorizationsData->list as $authorization)
                                        <tr>
                                            <td class="border px-4 py-2">{{ $authorization->name }}</td>
                                            <td class="border px-4 py-2">{{ $authorization->value }}</td>

                                            @if($hasVerifiedProperties)
                                                <td class="border px-4 py-2">
                                                    @if($authorization->isVerified())
                                                        @php
                                                        $authorizationLimit = $authorization->getFreeLimit($freeLimit);
                                                        @endphp
                                                        {{ Number::format(min($authorizationLimit, $authorization->getFreeCount())) }} / {{ Number::format($authorizationLimit) }}
                                                    @else
                                                        <svg class="text-gray-500" width="24px" height="24px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                        </svg>
                                                    @endif
                                                </td>
                                                <td class="border px-4 py-2">
                                                    @if($authorization->isVerified())
                                                        {{ $authorization->getPaidCount() ?: 0 }}
                                                    @else
                                                        <svg class="text-gray-500" width="24px" height="24px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                        </svg>
                                                    @endif
                                                </td>
                                                <td class="border px-4 py-2">
                                                    @if($authorization->isVerified())
                                                        {{ $authorization->getBlockedCount() ?: 0 }}
                                                    @else
                                                        <svg class="text-gray-500" width="24px" height="24px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                                                        </svg>
                                                    @endif
                                                </td>
                                            @endif

                                            <td class="border px-4 py-2">
                                                <div class="flex">
                                                    @if($authorization->isVerified())
                                                        <x-jet-dropdown align="right" width="48">
                                                            <x-slot name="trigger">
                                                                <x-jet-secondary-button class="mr-4">
                                                                    <div>{{ __('Options') }}</div>

                                                                    <div class="ml-1">
                                                                        <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                                                        </svg>
                                                                    </div>
                                                                </x-jet-secondary-button>
                                                            </x-slot>

                                                            <x-slot name="content">
                                                                <x-jet-dropdown-link href="{{ route('exonerate', ['properties' => $authorization->value]) }}">
                                                                    {{ __('Exonerate') }}
                                                                </x-jet-dropdown-link>
                                                            </x-slot>
                                                        </x-jet-dropdown>
                                                    @endif

                                                    <form method="POST" action="{{ route('remove-authorization') }}">
                                                        @csrf

                                                        <input type="hidden" name="_method" value="delete" />
                                                        <input type="hidden" name="value" value="{{ $authorization->value }}" />

                                                        <x-jet-secondary-button
                                                            type="submit"
                                                            onclick="if (!confirm('{{ __('Remove property :property', ['property' => $authorization->value]) }}')) { event.preventDefault(); event.stopPropagation(); return false; }"
                                                            {{-- wire:click="$toggle('confirmingPropertyDeletion{{ $authorization->id }}')" --}}
                                                            title="{{ __('Remove') }}"
                                                            aria-label="{{ __('Remove') }}"
                                                            class="text-red-800 hover:bg-red-500 hover:text-white active:bg-red-900 focus:border-red-900"
                                                        >
                                                            <svg class="fill-current" style="margin: -3px;" height="24" viewBox="0 0 48 48" width="24" xmlns="http://www.w3.org/2000/svg">
                                                                <path d="M0 0h48v48H0V0z" fill="none"/>
                                                                <path d="M12 38c0 2.2 1.8 4 4 4h16c2.2 0 4-1.8 4-4V14H12v24zm4.93-14.24l2.83-2.83L24 25.17l4.24-4.24 2.83 2.83L26.83 28l4.24 4.24-2.83 2.83L24 30.83l-4.24 4.24-2.83-2.83L21.17 28l-4.24-4.24zM31 8l-2-2H19l-2 2h-7v4h28V8z"/>
                                                                <path d="M0 0h48v48H0z" fill="none"/>
                                                            </svg>
                                                        </x-jet-secondary-button>

                                                        {{--
                                                        <x-jet-confirmation-modal wire:model="confirmingPropertyDeletion{{ $authorization->id }}">
                                                            <x-slot name="title">
                                                                {{ __('Remove property :property', ['property' => $authorization->value]) }}
                                                            </x-slot>

                                                            <x-slot name="content">
                                                                {{ __('Are you sure you want to delete this property?') }}
                                                            </x-slot>

                                                            <x-slot name="footer">
                                                                <x-jet-secondary-button wire:click="$toggle('confirmingPropertyDeletion{{ $authorization->id }}')" wire:loading.attr="disabled">
                                                                    {{ __('Keep') }}
                                                                </x-jet-secondary-button>

                                                                <x-jet-danger-button class="ml-2" type="submit" wire:loading.attr="disabled">
                                                                    {{ __('Remove') }}
                                                                </x-jet-danger-button>
                                                            </x-slot>
                                                        </x-jet-confirmation-modal>
                                                        --}}
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endforeach
                @else
                    <p class="my-4 px-6 py-4 bg-blue-300 border-blue-500">
                        {{ __('First, please register your website or server and give it a name.') }}
                    </p>
                @endif

                <form method="POST" action="{{ route('add-authorization') }}">
                    @csrf

                    <div>
                        <x-jet-label for="name" value="{{ __('Name') }}" />
                        <x-jet-input
                            id="name"
                            class="block mt-1 w-full"
                            type="text"
                            name="name"
                            :value="$name"
                            :autofocus="!$authorizationsCount && !old('name')"
                            required
                        />
                    </div>

                    @if(count($authorizations) > 1)
                        <div class="mt-4">
                            <div>{{ __('Type') }}</div>
                            <div>
                                @foreach($authorizations as $authorizationsData)
                                    <label>
                                        <input type="radio" name="type" value="{{ $authorizationsData->type }}" {!! $type === $authorizationsData->type || (($authorizationsData->default ?? false) && !$type) ? 'checked' : '' !!} />
                                        {{ $authorizationsData->name }}
                                    </label>
                                    &nbsp;
                                @endforeach
                            </div>
                            <div class="text-gray-400">
                                {{ __('You can use either a domain (or sub-domain) if you use VICOPO on a webpage accessed any users to allow more requests using the given domain as referer, or an IP address if you use VICOPO from a single machine having a fixed IP (if the IP is not fixed, you can still modify REFERER HTTP header and so use the "Domain" type).') }}
                            </div>
                        </div>
                    @endif

                    @foreach($authorizations as $authorizationsData)
                        <div class="mt-4" data-type="{{ $authorizationsData->type }}">
                            <x-jet-label for="{{ $authorizationsData->type }}" value="{{ $authorizationsData->name }}" />
                            <x-jet-input
                                id="{{ $authorizationsData->type }}"
                                class="block mt-1 w-full {{ isset($authorisationsErrors[$authorizationsData->type]) ? 'border-red-500' : '' }}"
                                type="text"
                                name="{{ $authorizationsData->type }}"
                                :value="$authorizationsData->value"
                                required
                            />

                            @if(isset($authorisationsErrors[$authorizationsData->type]))
                                @switch($authorisationsErrors[$authorizationsData->type])
                                    @case('format')
                                        <p class="text-red-500">{{ __('Invalid format.') }}</p>
                                        @break
                                    @case('duplicate')
                                        <p class="text-red-500">{{ __('":value" is already registered.', ['value' => old($authorizationsData->type)]) }}</p>
                                        @break
                                    @default
                                        <p class="text-red-500">{{ __('Unknown error.') }}</p>
                                @endswitch
                            @endif

                            @if($authorizationsData->type === 'domain' && $subDomain && $subDomain !== $domain)
                                <p id="use-sub-domain" class="text-sm my-2">
                                    <a class="text-blue-700 hover:underline cursor-pointer" onclick="useSubDomain()">{!!
                                        __('Use the :subDomain precise sub-domain (to exclude other sub-domains of the same top level domain.', [
                                            'subDomain' => '<strong class="bold">' . htmlspecialchars($subDomain) . '</strong>',
                                        ])
                                    !!}</a>
                                </p>
                                <p id="use-tld-domain" class="text-sm my-2 hidden">
                                    <a class="text-blue-700 hover:underline cursor-pointer" onclick="useTldDomain()">{!!
                                        __('Use the top level domain :domain to include any sub-domain.', [
                                            'domain' => '<strong class="bold">' . htmlspecialchars($domain) . '</strong>',
                                        ])
                                    !!}</a>
                                </p>
                            @endif
                        </div>
                    @endforeach

                    <div class="flex items-center justify-end mt-4">
                        <x-jet-button class="ml-4">
                            {{ __('Add authorization') }}
                        </x-jet-button>
                    </div>
                </form>

                <div class="mt-6 text-sm">
                    *{{ __('Per property counts are given from first to last month of the day midnight, Paris hours; while plan requests are counted from the subscription date and time.') }}
                </div>
            </div>
        </div>

        <script>
            function useSubDomain() {
                document.getElementById('domain').value = <?= json_encode($subDomain) ?>;
                document.getElementById('use-sub-domain').classList.add('hidden');
                document.getElementById('use-tld-domain').classList.remove('hidden');
            }

            function useTldDomain() {
                document.getElementById('domain').value = <?= json_encode($domain) ?>;
                document.getElementById('use-sub-domain').classList.remove('hidden');
                document.getElementById('use-tld-domain').classList.add('hidden');
            }

            function showTypeBlock() {
                var typeElement = document.querySelector('[name="type"]:checked');

                if (!typeElement) {
                    return;
                }

                var type = typeElement.value;

                [].forEach.call(document.querySelectorAll('[data-type]'), function (block) {
                    var blockType = block.getAttribute('data-type');
                    var visible = blockType === type;
                    block.style.display = visible ? 'block' : 'none';
                    document.getElementById(blockType).required = visible;
                });
            }
            showTypeBlock();

            [].forEach.call(document.querySelectorAll('[name="type"]'), function (input) {
                input.addEventListener('change', showTypeBlock);
            });
        </script>
    </div>
</x-app-layout>

