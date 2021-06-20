<div style="padding: 15px; margin: 15px; font-family: sans-serif;">
    {{ $title }}<br /><br />
    {!! nl2br(e($content)) !!}<br /><br />
    <a href="{{ $link }}" style="
        background-color: #1c64f2;
        text-transform: uppercase;
        color: #ffffff;
        font-weight: 600;
        padding: 8px 16px;
        cursor: pointer;
        border-radius: 6px;
    ">{{ __('Raise account total limit') }}</a><br /><br />
    {{ config('app.name') }}
</div>

@if(!empty($properties))
    <pre>{{ json_encode($properties, JSON_PRETTY_PRINT) }}</pre>
@endif
