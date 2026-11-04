<?php

declare(strict_types=1);

namespace Pgs\LaravelAiChatbot\Tools;

use Illuminate\Contracts\Auth\Access\Gate;
use Pgs\LaravelAiChatbot\Contracts\ToolInterface;

final class ToolAuthorizationService
{
    public function __construct(private readonly Gate $gate) {}

    public function allows(ToolInterface $tool, mixed $actor): bool
    {
        $requirements = $tool->authorizationRequirements();
        if (($requirements['public'] ?? false) === true) {
            return true;
        }

        if (! is_object($actor)) {
            return false;
        }

        foreach ($requirements['abilities'] ?? [] as $ability) {
            if (! $this->gate->forUser($actor)->check($ability)) {
                return false;
            }
        }

        return true;
    }
}
