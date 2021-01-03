<x-guest-layout>
    {{-- Strong Customer Authentication (SCA) --}}

    <script src="https://js.stripe.com/v3/"></script>

    <div class="flex" style="min-height: 100vh; overflow: hidden;">
        <div class="m-auto text-center items-center">
            @include('partials.spinner')
        </div>

        <form action="{{ route('confirm-intent') }}" method="post" id="confirm-form" style="width: 0; height: 0; overflow: hidden;">
            @csrf

            <input type="hidden" name="intent" value="{{ $intent->id }}" />

            <input type="submit" value="{{ __('Validate') }}" style="opacity: 0; pointer-events: none; height: 0; padding: 0;">
        </form>

        <form action="{{ route('reject-intent') }}" method="post" id="reject-form" style="width: 0; height: 0; overflow: hidden;">
            @csrf

            <input type="hidden" name="intent" value="{{ $intent->id }}" />

            <input type="submit" value="{{ __('Validate') }}" style="opacity: 0; pointer-events: none; height: 0; padding: 0;">
        </form>
    </div>

    <script>
        var stripe = Stripe('{{ config('stripe.publishable_key') }}');
        var elements = stripe.elements();
        var style = {
            base: {
                color: '#32325d',
                fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                fontSmoothing: 'antialiased',
                fontSize: '16px',
                '::placeholder': {
                    color: '#aab7c4'
                }
            },
            invalid: {
                color: '#fa755a',
                iconColor: '#fa755a'
            }
        };

        stripe.confirmCardPayment({!! json_encode($intent->client_secret) !!}).then(function (result) {
            if ((result.paymentIntent || {}).status !== 'succeeded') {
                var form = document.getElementById('reject-form');
                var hiddenInput = document.createElement('input');
                hiddenInput.setAttribute('type', 'hidden');
                hiddenInput.setAttribute('name', 'error');
                hiddenInput.setAttribute('value', (result.error || {}).message);
                form.appendChild(hiddenInput);
                form.submit();

                return;
            }

            document.getElementById('confirm-form').submit();
        });
    </script>
</x-guest-layout>
