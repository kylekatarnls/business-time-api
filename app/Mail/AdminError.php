<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class AdminError extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param array $data
     */
    public function __construct(private array $data)
    {
        $this->data['exceptions'] = array_map(static fn(Throwable $exception) => (object) [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'stack' => $exception->getTraceAsString(),
        ], $this->data['exceptions']);
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): self
    {
        $appName = config('app.name');

        return $this->from('no-reply@selfbuild.fr', $appName)
            ->subject(__(':appName error', ['appName' => $appName]))
            ->view('emails.admin-error', $this->data);
    }
}
