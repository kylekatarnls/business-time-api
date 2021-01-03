<div style="padding: 15px; margin: 15px; font-family: sans-serif;">
    {{ __('Thanks for your message, we\'ll get in touch quickly.') }}<br /><br />
    Vicopo
</div>

@if(!empty($properties))
    <pre>{{ json_encode($properties, JSON_PRETTY_PRINT) }}</pre>
@endif

<div style="padding: 15px; margin: 15px; color: gray; font-family: sans-serif;">
    {!! nl2br(e($content)) !!}
</div>
