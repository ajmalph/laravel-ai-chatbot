<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Exceptions;

use RuntimeException;

final class ConversationAccessException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly int $status)
    {
        parent::__construct($errorCode);
    }
}
