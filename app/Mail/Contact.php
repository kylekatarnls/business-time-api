<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class Contact extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param array{content: string, properties?: string[], template?: string} $data
     * @param string|null $initialSubject
     *
     * @return void
     */
    public function __construct(array $data, private ?string $initialSubject = null)
    {
        $this->viewData = $data;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): self
    {
        return $this->from('no-reply@selfbuild.fr', config('app.name'))
            ->subject($this->initialSubject ?? __('Message Confirmation'))
            ->view('emails.contact');
    }
}
