<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Log;

interface LogAwareBusinessEventInterface
{
    public function getLogData(): array;

    /**
     * @return 100|200|250|300|400|500|550|600
     */
    public function getLogLevel(): int;
}
