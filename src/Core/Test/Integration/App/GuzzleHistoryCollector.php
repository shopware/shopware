<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\App;

use GuzzleHttp\Middleware;

/**
 * @internal
 */
class GuzzleHistoryCollector
{
    /**
     * @var list<array<string, mixed>>
     */
    private static array $historyContainer;

    public function getHistoryMiddleWare(): callable
    {
        self::$historyContainer = [];

        /** @phpstan-ignore assign.propertyType (Guzzle has some improper parameter type annotation) */
        return Middleware::history(self::$historyContainer);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getHistory(): array
    {
        return self::$historyContainer;
    }

    public function resetHistory(): void
    {
        // Reconstructing the array does not break the reference in the middleware.
        self::$historyContainer = [];
    }
}
