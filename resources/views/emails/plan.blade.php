<div style="padding: 15px; margin: 15px; font-family: sans-serif;">
    {{ __('Subscription successfully enabled!') }}<br /><br />
    Vicopo
</div>

@if(!empty($properties))
    <pre>{{ json_encode($properties, JSON_PRETTY_PRINT) }}</pre>
@endif

<div style="padding: 15px; margin: 15px; color: #0b2e13; font-family: sans-serif;">
    {!! nl2br(e($content)) !!}
</div>
