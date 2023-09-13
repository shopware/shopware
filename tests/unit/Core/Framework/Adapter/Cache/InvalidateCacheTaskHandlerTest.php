<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTask;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTaskHandler
 */
class InvalidateCacheTaskHandlerTest extends TestCase
{
    public function testGetHandledMessages(): void
    {
        static::assertEquals([InvalidateCacheTask::class], InvalidateCacheTaskHandler::getHandledMessages());
    }

    public function testRunWithoutDelay(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects(static::once())->method('invalidateExpired')->with(null);

        $handler = new InvalidateCacheTaskHandler($this->createMock(EntityRepository::class), $cacheInvalidator, 0);
        $handler->run();
    }

    public function testRunWithDelay(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects(static::once())->method('invalidateExpired');

        $handler = new InvalidateCacheTaskHandler($this->createMock(EntityRepository::class), $cacheInvalidator, 300);
        $handler->run();
    }

    public function testRunDoesCatchException(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects(static::once())
            ->method('invalidateExpired')
            ->with(null)
            ->willThrowException(new \Exception());

        $handler = new InvalidateCacheTaskHandler($this->createMock(EntityRepository::class), $cacheInvalidator, 0);
        $handler->run();
    }
}
