<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

final class PlanChange extends Mailable
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
        return $this->from('no-reply@selfbuild.fr', 'Vicopo')
            ->subject($this->initialSubject ?? __('Plan Change Confirmation'))
            ->view('emails.plan');
    }
}
