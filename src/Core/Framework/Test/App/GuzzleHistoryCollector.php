<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App;

use GuzzleHttp\Middleware;

class GuzzleHistoryCollector
{
    /**
     * @var array
     */
    private static $historyContainer;

    public function getHistoryMiddleWare()
    {
        self::$historyContainer = [];

        return Middleware::history(self::$historyContainer);
    }

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
