@php
    $template = ($template ?? 'contact');
    $exonerate = ($template === 'exonerate');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                {{ $exonerate ? __('Exoneration') : __('Contact') }}
            </h2>

            @unless(Auth::user())
                <a class="
                    ml-16 items-center px-1 pt-1 border-b-2 border-transparent
                    text-sm font-medium leading-5 text-gray-500
                    hover:text-gray-700 hover:border-gray-300
                    focus:outline-none focus:text-gray-700 focus:border-gray-300
                    transition duration-150 ease-in-out
                " href="/">
                    {{ __('Documentation') }}
                </a>
            @endunless
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                @if($sent)
                    <div class="message-sent-feedback px-6 py-4 mb-6 bg-green-300 border-green-500">
                        {{ __('Message sent.') }}
                        <div class="mt-2">
                            <a
                                class="
                                    px-4 py-2 rounded-md
                                    bg-green-600 hover:bg-green-500
                                    font-semibold text-xs text-white uppercase
                                "
                                href="#"
                                onclick="
                                    document.querySelector('.contact-form').style.display = 'block';
                                    document.querySelector('.message-sent-feedback').style.display = 'none';
                                    event.preventDefault();
                                    event.stopPropagation();
                                    return false;
                                "
                            >{{ __('OK') }}</a>
                        </div>
                    </div>
                @endif

                @if($exonerate)
                    <div class="px-6 py-4 mb-6 bg-blue-200 border-blue-400">
                        {{ __('Business-Time API remains free (up to 200 000 requests per month) for:') }}
                        <ul class="list-disc ml-5 my-2">
                            <li>{{ __('websites/softwares dedicated to charity*,') }}</li>
                            <li>{{ __('non-profit organizations*,') }}</li>
                            <li>{{ __('small companies from tourism sector for the whole year 2021*,') }}</li>
                        </ul>
                        {{ __('If you are in any of those categories, please describe your organization below:') }}
                    </div>
                @endif

                <form
                    class="contact-form"
                    method="POST"
                    action="{{ route('post-contact') }}"
                    onsubmit="this.querySelector('.send-button').disabled = true"
                    style="display: {{ $sent ? 'none' : 'block' }}"
                >
                    @csrf

                    <input type="hidden" name="template" value="{{ $template }}">

                    <div class="mt-2">
                        <x-jet-label for="email" value="{{ __('Email') }}" />
                        <x-jet-input
                            id="email"
                            class="block mt-1 w-full"
                            type="email"
                            name="email"
                            :value="$email"
                            required
                            :autofocus="!$email"
                        />
                    </div>

                    @if($exonerate)
                        <div class="mt-2">
                            <x-jet-label for="properties" value="{{ __('Domains/IPs to exonerate') }}" />
                            <select
                                id="properties"
                                name="properties"
                                multiple
                                class="form-input rounded-md shadow-sm mt-1 block mt-1 w-full"
                            >
                                @foreach($properties as $property)
                                    <option
                                        value="{{ $property->id }}/{{ $property->type }}/{{ $property->value }}"
                                        @if(in_array($property->value, $selectedProperties))
                                            selected
                                        @endif
                                    >{{ $property->value }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="mt-2">
                        <x-jet-label for="message" value="{{ $exonerate ? __('Description') : __('Message') }}" />
                        <textarea
                            id="message"
                            class="form-input rounded-md shadow-sm mt-1 block mt-1 w-full"
                            name="message"
                            required
                            {{ $email ? 'autofocus' : '' }}
                        >{{ old('message') }}</textarea>
                    </div>

                    <div class="flex items-center justify-end mt-4">
                        <x-jet-button class="ml-4" class="send-button">
                            {{ $exonerate ? __('Submit') : __('Send') }}
                        </x-jet-button>
                    </div>
                </form>

                @if($exonerate)
                    <div class="my-6">
                        {{ __('Our purpose in keeping Business-Time API free under 5 000 requests per month and providing on demand exoneration is to make the software sustainable for the highest number of people while subscriptions will allow us to provide a long-term support, maintenance and new features. If you think there is something else regarding this or your organization, please feel free to tell us via this form.') }}
                    </div>
                    <div class="my-6">
                        *{{ __('Under reserve of validity and acceptation of the request based on the website main activity (or "whois" information for IP). We might ask for further proof of the identity, activity, and the necessity for higher requests limits.') }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>

<script>
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
