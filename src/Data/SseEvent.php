<?php

declare(strict_types=1);

namespace Crontinel\Data;

/**
 * Simple DTO representing a parsed Server-Sent Event.
 */
final class SseEvent
{
    public string $event = '';
    public string $data = '';
    public string $id = '';
}
