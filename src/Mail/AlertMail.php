<?php

declare(strict_types=1);

namespace Crontinel\Mail;

use Illuminate\Mail\Mailable;

class AlertMail extends Mailable
{
    public function __construct(
        public readonly string $alertTitle,
        public readonly string $alertMessage,
    ) {}

    public function build(): static
    {
        return $this
            ->subject("[Crontinel] {$this->alertTitle}")
            ->text('crontinel::emails.alert');
    }
}
