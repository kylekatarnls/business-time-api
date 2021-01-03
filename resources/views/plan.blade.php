<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Plan') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 relative spinner-slot">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6 disable-on-load">
                @if($paymentError)
                    <div class="px-6 py-4 mb-6 bg-red-300 border-red-500">
                        {{ $paymentError }}
                    </div>
                    <script>
                        _paq.push(['trackEvent', 'Payment error', '{{ $selectedPlan }} - {{ $selectedRecurrence }} / {{ $paymentError }}']);
                    </script>
                @endif
                @if($canceled)
                    <div class="px-6 py-4 mb-6 bg-red-300 border-red-500">
                        {{ __("Subscription to :plan couldn't succeed.", ['plan' => $canceled]) }}
                    </div>
                    <script>
                        _paq.push(['trackEvent', 'Payment canceled', '{{ $selectedPlan }} - {{ $selectedRecurrence }}']);
                    </script>
                @endif
                @if($credit > 0)
                    <div class="px-6 py-4 mb-6 bg-blue-300 border-blue-500">
                        <p>
                            {{ __('Your current active subscription gives you a credit of :credit to be deduced from your next subscriptions.', [
                                'credit' => price(Number::format($credit, 2), $creditCurrency),
                            ]) }}
                        </p>
                    </div>
                @endif
                <div class="flex content-center">
                    @foreach($plans as $id => $plan)
                        <div
                            class="w-1/{{ $numberOfPlans }} text-center rounded-lg p-4"
                            data-plan-preview="{{ $id }}"
                            data-monthly-price="{{ $plan['monthly_price'] }}"
                            data-yearly-price="{{ $plan['yearly_price'] }}"
                            data-yearly-saving="{{ $plan['yearly_saving'] }}"
                        >
                            <h3 class="text-blue-600 font-bold">{{ $plan['name'] }}</h3>

                            <div class="m2 text-italic plan-description">{{ $plan['description'] }}</div>

                            <p class="price">{{ price($plan['monthly_price'], $plan['currency']) }}</p>
                            <p class="text-sm">{{ __('/ month') }}</p>
                            <p class="text-sm">{{ __('or :price / year', [
                                'price' => price($plan['yearly_price'], $plan['currency']),
                            ]) }}</p>

                            @if($plan['subscribed'])
                                <div
                                    class="inline-block w-1/2 px-4 py-3 mt-4 text-white rounded-lg bg-gray-400"
                                >
                                    {{ __('Active') }}
                                </div>
                                <div class="text-sm mt-1">
                                    <a
                                        href="#payment-form"
                                        data-select-plan="{{ $id }}"
                                        data-select-recurrence="{{ $plan['recurrence'] === 'monthly' ? 'yearly' : 'monthly' }}"
                                        class="text-blue-600 underline"
                                    >
                                        {{ __('Switch to :recurrence billing', [
                                            'recurrence' => $plan['recurrence'] === 'monthly' ? __('yearly') : __('monthly'),
                                        ]) }}
                                    </a>
                                </div>
                            @else
                                <button
                                    data-select-plan="{{ $id }}"
                                    class="inline-block w-1/2 px-4 py-3 mt-4 text-white rounded-lg bg-blue-500 hover:bg-blue-400"
                                >
                                    {{ __('Subscribe') }}
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>
                <script src="https://js.stripe.com/v3/"></script>

                <form action="{{ route('subscribe') }}" method="post" id="payment-form" class="buy-form p-6 rounded-lg">
                    @csrf

                    <input type="hidden" name="plan" id="plan-selection" />

                    <div class="form-row mb-5">
                        <label>
                            <input type="radio" name="recurrence" value="yearly" checked />
                            {{ __('Yearly') }}
                            <strong>{!! price('<span id="yearly-price"></span>') !!}</strong>
                            &nbsp; <span class="text-sm">{{ __('Saving:') }}</span> <span class="text-green-500">-{!! price('<span id="yearly-saving"></span>') !!}</span>
                        </label>
                    </div>

                    <div class="form-row mb-5">
                        <label>
                            <input type="radio" name="recurrence" value="monthly" />
                            {{ __('Monthly') }}
                            <strong>{!! price('<span id="monthly-price"></span>') !!}</strong>
                        </label>
                    </div>

                    <div class="form-row">
                        @if($user->card_brand)
                            <p class="text-sm italic">
                                {{ __('Credit or debit card') }}
                            </p>

                            <div class="card-choice my-5">
                                <label class="flex">
                                    <input class="my-auto" type="radio" name="card" value="default" checked />
                                    <img
                                        class="my-auto mx-2 rounded bg-gray-300"
                                        src="{{ $user->getCardIcon() }}"
                                        alt="{{ ucfirst($user->card_brand) }}"
                                        width="64" height="40"
                                    />
                                    <strong class="my-auto">**** **** **** {{ $user->card_last_four }}</strong>
                                </label>
                            </div>
                            <div class="card-choice">
                                <div class="my-5">
                                    <label>
                                        <input type="radio" name="card" value="new" />
                                        {{ __('New card') }}
                                    </label>
                                </div>

                                <div class="card-choice-details hidden" id="card-element"></div>
                            </div>
                        @else
                            <label for="card-element">
                                {{ __('Credit or debit card') }}
                            </label>

                            <div id="card-element"></div>
                        @endif

                        <div id="card-errors" role="alert" class="px-6 py-4 mt-4 bg-red-300 border-red-500 rounded-lg"></div>
                    </div>

                    <button class="inline-block w-full px-4 py-3 mt-4 text-white rounded-lg bg-blue-500 hover:bg-blue-400">
                        {{ __('Validate') }}
                    </button>
                </form>

                <p class="mt-6">{{ __('The requests count is added to your account and shared across your domains and IPs if you record multiple ones. It will be reset every month.') }}</p>

                <p class="mt-6">{{ __('Any subscription can be stopped at any moment, refunding to you the remaining credit subtracted from :fees of closure fees.', [
                    'fees' => price($closureFees),
                ]) }}</p>

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

                    var card = elements.create('card', {
                        hidePostalCode: true,
                        style: style
                    });

                    card.mount('#card-element');

                    card.on('change', function (event) {
                        var errors = document.getElementById('card-errors');
                        errors.textContent = event.error ? event.error.message : '';
                        errors.style.display = event.error ? 'block' : 'none';
                    });

                    function displayLoading() {
                        [].forEach.call(document.querySelectorAll('.disable-on-load'), function (block) {
                            block.style.opacity = 0.5;
                            block.style.pointerEvents = 'none';

                            block.parentNode.querySelector('.spinner').classList.remove('hidden');
                        });
                    }

                    function hideLoading() {
                        [].forEach.call(document.querySelectorAll('.disable-on-load'), function (block) {
                            block.style.opacity = 1;
                            block.style.pointerEvents = '';

                            block.parentNode.querySelector('.spinner').classList.add('hidden');
                        });
                    }

                    var form = document.getElementById('payment-form');
                    form.addEventListener('submit', function (event) {
                        displayLoading();

                        var defaultCardCheckbox = document.querySelector('[name="card"][value="default"]');

                        if (defaultCardCheckbox && defaultCardCheckbox.checked) {
                            return;
                        }

                        event.preventDefault();

                        stripe.createPaymentMethod({
                            type: 'card',
                            card: card,
                            billing_details: {
                                @if($user?->name)
                                    name: {!! json_encode($user->name) !!},
                                @endif
                                @if($user?->email)
                                    email: {!! json_encode($user->email) !!},
                                @endif
                            },
                        }).then(function (result) {
                            if (result.error) {
                                hideLoading();
                                document.getElementById('card-errors').textContent = result.error.message;

                                return;
                            }

                            stripeTokenHandler(result.paymentMethod);
                        });
                    });

                    function stripeTokenHandler(paymentMethod) {
                        var form = document.getElementById('payment-form');
                        var hiddenInput = document.createElement('input');
                        hiddenInput.setAttribute('type', 'hidden');
                        hiddenInput.setAttribute('name', 'stripePaymentMethod');
                        hiddenInput.setAttribute('value', paymentMethod.id);
                        form.appendChild(hiddenInput);

                        displayLoading();

                        form.submit();
                    }

                    function selectPlan(button, track = false) {
                        var planId = button.getAttribute('data-select-plan');
                        var recurrence = button.getAttribute('data-select-recurrence');

                        if (track) {
                            _paq.push(['trackEvent', 'Select plan', planId]);
                        }

                        [].forEach.call(document.querySelectorAll('[data-plan-preview]'), function (plan) {
                            plan.style.opacity = 0.5;
                            plan.style.background = 'none';
                            plan.style.filter = 'grayscale(1)';
                        });

                        [].forEach.call(document.querySelectorAll('input[name="recurrence"]'), function (input) {
                            input.disabled = {!!
                                $currentRecurrence && $currentPlanId
                                    ? '(planId === "' . $currentPlanId . '" && input.value === "' . $currentRecurrence . '")'
                                    : 'false'
                            !!};

                            if (input.value === recurrence) {
                                input.click();
                            }
                        });

                        var selectedPlan = document.querySelector('[data-plan-preview="' + planId + '"]');
                        selectedPlan.style.opacity = 1;
                        selectedPlan.style.background = '#f3f5f5';
                        selectedPlan.style.filter = '';

                        document.querySelector('#plan-selection').value = planId;
                        document.querySelector('#monthly-price').innerText = selectedPlan.getAttribute('data-monthly-price');
                        document.querySelector('#yearly-price').innerText = selectedPlan.getAttribute('data-yearly-price');
                        document.querySelector('#yearly-saving').innerText = selectedPlan.getAttribute('data-yearly-saving');

                        var form = document.querySelector('#payment-form');

                        if (!/open/.test(form.className)) {
                            form.className += ' open';
                        }
                    }

                    [].forEach.call(document.querySelectorAll('[data-select-plan]'), function (button) {
                        button.addEventListener('click', function () {
                            selectPlan(button, true);
                        });
                    });
                    [].forEach.call(document.querySelectorAll('[name="card"]'), function (checkbox) {
                        ['click', 'focus', 'blur', 'change'].forEach(function (event) {
                            checkbox.addEventListener(event, function () {
                                document.getElementById('card-element').style.display = document.querySelector('[name="card"][value="default"]').checked
                                    ? 'none'
                                    : 'block';
                            });
                        });
                    });

                    @if($selectedPlan)
                        selectPlan(document.querySelector('[data-select-plan="{{ $selectedPlan }}"]'));

                        @if($selectedRecurrence)
                            document.querySelector('input[name="recurrence"][value="{{ $selectedRecurrence }}"]').click();
                        @endif

                        @if($selectedCard)
                            document.querySelector('input[name="card"][value="{{ $selectedCard }}"]').click();
                        @endif
                    @endif
                </script>
            </div>
            @include('partials.spinner', ['hidden' => true])
        </div>
    </div>
</x-app-layout>
