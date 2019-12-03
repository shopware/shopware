<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

interface LogAwareBusinessEventInterface
{
    public function getLogData(): array;

    public function getLogLevel(): int;
}
