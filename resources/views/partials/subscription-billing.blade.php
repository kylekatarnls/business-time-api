<div>
    @if($end)
        <p class="my-2 text-red-500">{{ __('Subscription end: :date', ['date' => $end]) }}</p>
        <p class="my-2">
            <a
                href="{{ route('autorenew') }}"
                class="
                    px-4 py-2 rounded-md
                    bg-green-600 hover:bg-green-500
                    font-semibold text-xs text-white uppercase
                "
            >
                {{ __('Activate auto-renew') }}
            </a>
        </p>
    @else
        <p class="mt-1 text-sm">{{ __('Next billing: :date', ['date' => $date]) }}</p>
        <p>
            <a
                class="text-sm text-gray-500 underline cursor-pointer"
                wire:click="confirmSubscriptionCancellation"
            >{{ __('Stop the subscription (cancel renewing on term).') }}</a>
        </p>
    @endif

    <x-jet-confirmation-modal wire:model="confirmingSubscriptionCancellation">
        <x-slot name="title">
            {{ __('Subscription termination') }}
        </x-slot>

        <x-slot name="content">
            {{ __('Any requests (over free quota) happening after :dateAndTime will be blocked if you cancel your subscription. Please confirm clicking on "Terminate" or cancel the termination by clicking on "Renew".', [
                'dateAndTime' => $dateTime,
            ]) }}
        </x-slot>

        <x-slot name="footer">
            <x-jet-secondary-button wire:click="$toggle('confirmingSubscriptionCancellation')" wire:loading.attr="disabled">
                {{ __('Keep subscription (renewed on term)') }}
            </x-jet-secondary-button>

            <x-jet-danger-button class="ml-2" wire:click="cancelSubscription" wire:loading.attr="disabled">
                {{ __('Terminate subscription on term') }}
            </x-jet-danger-button>
        </x-slot>
    </x-jet-confirmation-modal>
</div>
