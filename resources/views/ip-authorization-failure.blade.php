<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Server verification failure') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg p-6">
                <p class="my-4 p-3 bg-red-100">
                    {{ $error }}
                </p>

                <p class="my-4">{{ trans_choice('IP address seen: :ips|IP addresses seen: :ips', count($ips), ['ips' => implode(', ', $ips)]) }}</p>

                @if($ip)
                    <p class="my-4">{{ __('Expected IP address: :ip', ['ip' => $ip]) }}</p>
                @endif

                <p class="my-4">{{ __('Log in your server (using SSH for instance) and call the URL using this command:') }}</p>

                <div class="float-right copy-clipboard" onclick="copyCommand()">
                    <svg height="22px" version="1.1" viewBox="0 0 21 22" width="21px" xmlns="http://www.w3.org/2000/svg" xmlns:sketch="http://www.bohemiancoding.com/sketch/ns" xmlns:xlink="http://www.w3.org/1999/xlink">
                        <g fill="none" fill-rule="evenodd" stroke="none" stroke-width="1">
                            <g fill="#ffffff" transform="translate(-86.000000, -127.000000)">
                                <g transform="translate(86.500000, 127.000000)">
                                    <path d="M14,0 L2,0 C0.9,0 0,0.9 0,2 L0,16 L2,16 L2,2 L14,2 L14,0 L14,0 Z M17,4 L6,4 C4.9,4 4,4.9 4,6 L4,20 C4,21.1 4.9,22 6,22 L17,22 C18.1,22 19,21.1 19,20 L19,6 C19,4.9 18.1,4 17,4 L17,4 Z M17,20 L6,20 L6,6 L17,6 L17,20 L17,20 Z" />
                                </g>
                            </g>
                        </g>
                    </svg>
                </div>

                <textarea
                    id="curl-command"
                    class="my-4 p-3 bg-gray-900 text-gray-400 font-mono block w-full"
                    rows="1"
                    readonly
                >curl -s {{ $url }}</textarea>

                <p id="curl-command-copied" class="my-4 p-3 bg-green-100 hidden">
                    {{ __('Command copied.') }}
                </p>

                <script>
                    function copyCommand() {
                        var copyText = document.getElementById('curl-command');

                        copyText.select();
                        copyText.setSelectionRange(0, 99999);
                        document.execCommand("copy");

                        var feedback = document.getElementById('curl-command-copied');
                        feedback.className = feedback.className.replace(/hidden/g, '');
                    }
                </script>
            </div>
        </div>
    </div>
</x-app-layout>
