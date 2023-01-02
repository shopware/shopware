<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution\Awareness;

/**
 * @internal
 * @package core
 */
trait StoppableHookTrait
{
    protected bool $isPropagationStopped = false;

    public function stopPropagation(): void
    {
        $this->isPropagationStopped = true;
    }

    public function isPropagationStopped(): bool
    {
        return $this->isPropagationStopped;
    }
}
