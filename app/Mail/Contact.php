<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class Contact extends Mailable
{
    use Queueable, SerializesModels;

    private array $data;

    private ?string $initialSubject;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(array $data, ?string $subject = null)
    {
        $this->data = $data;
        $this->initialSubject = $subject;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build(): self
    {
        return $this->from('no-reply@selfbuild.fr', 'Vicopo')
            ->subject($this->initialSubject ?? __('Message Confirmation'))
            ->view('emails.contact', $this->data);
    }
}
