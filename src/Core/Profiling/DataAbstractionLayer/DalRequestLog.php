<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
interface DalRequestLog
{
    public function getName(): string;

    /**
     * @return array<string, int>
     */
    public function getRequestCounts(): array;

    /**
     * @return array{url: string, time: float, body: mixed, position: int}
     */
    public function getRequest(string $channel, int $index): array;
}
