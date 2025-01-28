<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Dbal;

use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
interface ExceptionHandlerInterface
{
    public const PRIORITY_DEFAULT = 0;

    public const PRIORITY_LATE = -10;

    public const PRIORITY_EARLY = 10;

    public function getPriority(): int;

    /**
     * @deprecated tag:v6.7.0 - in v6.7.0 return type will be changed to null|\Throwable
     *
     * @param \Exception $e - @deprecated tag:v6.7.0 - in v6.7.0 parameter type will be changed to \Throwable
     */
    public function matchException(\Exception $e): ?\Exception;
}
