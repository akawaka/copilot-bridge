<?php

declare(strict_types=1);

namespace Akawaka\Bridge\Copilot\Exception;

final class TokenException extends CopilotException
{
    public function __construct(string $message = 'Copilot token error', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}