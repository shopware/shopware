<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
interface ExceptionHandlerInterface
{
    public const PRIORITY_DEFAULT = 0;

    public const PRIORITY_LATE = -10;

    public const PRIORITY_EARLY = 10;

    public function getPriority(): int;

    public function matchException(\Exception $e): ?\Exception;
}
