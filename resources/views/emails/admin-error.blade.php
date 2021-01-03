@foreach($exceptions as $exception)
    <div style="padding-bottom: 15px; margin-bottom: 15px;">
        <pre><strong>{{ $exception->message }}</strong></pre>
        <pre style="color: navy;">{{ $exception->file }}:{{ $exception->line }}</pre>
        <pre>{{ $exception->stack }}</pre>
    </div>
@endforeach
