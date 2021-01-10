<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class PlanChange extends Mailable
{
    use Queueable, SerializesModels;

    private ?string $initialSubject;

    /**
     * Create a new message instance.
     *
     * @param array{content: string, properties?: string[]} $data
     *
     * @return void
     */
    public function __construct(array $data, ?string $subject = null)
    {
        $this->viewData = $data;
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
            ->subject($this->initialSubject ?? __('Plan Change Confirmation'))
            ->view('emails.plan');
    }
}
