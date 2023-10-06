<?php declare(strict_types=1);

namespace Shopware\Core\Test\Integration\App;

use GuzzleHttp\Middleware;

/**
 * @internal
 */
class GuzzleHistoryCollector
{
    /**
     * @var array<int, mixed>
     */
    private static array $historyContainer;

    public function getHistoryMiddleWare(): callable /* @phpstan-ignore-line callable can not be typed as it is recursive */
    {
        self::$historyContainer = [];

        return Middleware::history(self::$historyContainer);
    }

    /**
     * @return array<int, mixed>
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
