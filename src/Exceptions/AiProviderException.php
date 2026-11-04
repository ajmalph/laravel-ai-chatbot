<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Exceptions;

use RuntimeException;
use Throwable;

final class AiProviderException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly bool $retryable = false,
        ?Throwable $previous = null,
    ) {
        parent::__construct('The configured AI provider could not complete the request.', 0, $previous);
    }
}
