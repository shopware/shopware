<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution\Awareness;

/**
 * @internal
 *
 * @package core
 */
interface StoppableHook
{
    public function stopPropagation(): void;

    public function isPropagationStopped(): bool;
}
