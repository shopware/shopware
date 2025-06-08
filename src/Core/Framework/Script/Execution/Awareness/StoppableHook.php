<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Script\Execution\Awareness;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface StoppableHook
{
    public function stopPropagation(): void;

    public function isPropagationStopped(): bool;
}
