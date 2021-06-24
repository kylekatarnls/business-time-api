<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                @if($onBehalf)
                    <div><a href="{{ route('admin-users') }}">&lt; {{ __('Users') }}</a></div>
                    <h3 class="font-bold">
                        {{ $user->name }}
                        &nbsp;
                        {{ $user->id }}
                        &nbsp;
                        {{ $user->email }}
                        &nbsp;
                        {{ $user->getPlanId() }}
                    </h3>
                @endif

                @foreach($errors as $error)
                    <div class="px-6 py-4 mb-6 bg-red-300 border-red-500">
                        {{ $error }}
                    </div>
                @endforeach

                @if($plan)
                    @if($limit === INF)
                        <div class="my-4 p-3 bg-green-100">
                            <p>
                                <span class="py-1 px-3 mr-2 bg-green-700 text-white text-sm rounded-lg">{{ $plan['name'] }}</span>
                                {{ __('Total paid requests: :requests', [
                                   'requests' => Number::format($paidRequests),
                                ]) }}
                            </p>
                            @livewire('subscription-billing', $nextBill)
                        </div>
                    @elseif($limit)
                        <div class="my-4 p-3 bg-yellow-100">
                            <p>
                                <span class="py-1 px-3 mr-2 bg-green-700 text-white text-sm rounded-lg">{{ $plan['name'] }}</span>
                                {{ __('Total paid requests: :requests / :limit', [
                                    'requests' => Number::format($paidRequests),
                                    'limit' => Number::format($limit),
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
                            @livewire('subscription-billing', $nextBill)
                        </div>
                        <p class="my-4">
                            {{ __('Your :plan subscription allow you :requests more requests per month shared among your properties.', [
                                'plan' => $plan['name'],
                                'requests' => Number::format($limit),
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

                    @if($hitsDaysCount >= 1)
                        <div data-graph="{{ json_encode($hits) }}" style="height: {{ 220 + 25 * count($hits) }}px;"></div>
                        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jqPlot/1.0.8/jquery.jqplot.min.css">
                        <script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha256-/xUj+3OJU5yExlq6GSYGSHk7tPXikynS7ogEvDej/m4=" crossorigin="anonymous"></script>
                        <script src="/js/jquery.canvasjs.min.js"></script>
                        <script>
                            $('[data-graph]').each(function () {
                                var $graph = $(this);
                                var data = $graph.data('graph');
                                var properties = Object.keys(data);

                                $graph.CanvasJSChart({
                                    title: {
                                        text: "{{ trans_choice(
                                            'Yesterday|Last :count days',
                                            $hitsDaysCount,
                                            [':count' => $hitsDaysCount],
                                        ) }}"
                                    },
                                    animationEnabled: true,
                                    legend: {
                                        show: true,
                                        location: 'e',
                                        placement: 'outside'
                                    },
                                    data: properties.map(function (property) {
                                        var days = Object.keys(data[property]);

                                        return {
                                            type: 'line',
                                            name: property.replace(/^.+:/, ''),
                                            showInLegend: true,
                                            dataPoints: days.map(function (day) {
                                                return {
                                                    label: new Date(day + ' 00:00:00').toLocaleDateString(),
                                                    y: data[property][day]
                                                };
                                            })
                                        };
                                    })
                                });
                            });
                        </script>
                    @endif
                @endif

                <h3>{{ trans_choice('Your key|Your keys', count($keys)) }}</h3>

                @foreach($keys as $key)
                    <pre class="my-4 px-6 py-4 bg-gray-200 text-sm">{{ $key->key }}</pre>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>

