<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class LimitThreshold extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @param array{content: string, properties?: string[]} $data
     * @param string|null $initialSubject
     *
     * @return void
     */
    public function __construct(array $data)
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
        return $this->from('no-reply@selfbuild.fr', 'Vicopo')
            ->subject($this->viewData['title'])
            ->view('emails.limit-threshold');
    }
}
