<?php

declare(strict_types=1);

namespace Crontinel;

use Crontinel\Services\OutcomeBuffer;

class Outcome
{
    public static function metric(string $name, int|float $value): void
    {
        try {
            app(OutcomeBuffer::class)->metric($name, $value);
        } catch (\Throwable) {
            // A monitoring error must not fail the customer's job.
        }
    }

    public static function timestamp(string $name, string $value): void
    {
        try {
            app(OutcomeBuffer::class)->timestamp($name, $value);
        } catch (\Throwable) {
            // A monitoring error must not fail the customer's job.
        }
    }

    /** Text to send as this run's output. Truncated to 10,000 characters. */
    public static function output(string $text): void
    {
        try {
            app(OutcomeBuffer::class)->output($text);
        } catch (\Throwable) {
            // A monitoring error must not fail the customer's job.
        }
    }
}
