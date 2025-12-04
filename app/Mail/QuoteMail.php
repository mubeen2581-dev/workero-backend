<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuoteMail extends Mailable
{
    use Queueable, SerializesModels;

    public $quote;

    /**
     * Create a new message instance.
     */
    public function __construct(Quote $quote)
    {
        $this->quote = $quote->load('client', 'items', 'company');
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $subject = 'Your Quote from ' . ($this->quote->company->name ?? 'Workero');
        
        return $this->subject($subject)
                    ->view('emails.quote')
                    ->with([
                        'quote' => $this->quote,
                    ]);
    }
}


