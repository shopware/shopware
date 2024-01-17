<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * @internal
 */
#[CoversClass(InvalidateCacheTaskHandler::class)]
class InvalidateCacheTaskHandlerTest extends TestCase
{
    public function testRunWithoutDelay(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects(static::once())->method('invalidateExpired');

        $handler = new InvalidateCacheTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $cacheInvalidator,
            0
        );
        $handler->run();
    }

    public function testRunWithDelay(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects(static::once())->method('invalidateExpired');

        $handler = new InvalidateCacheTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $cacheInvalidator,
            300
        );
        $handler->run();
    }

    public function testRunDoesCatchException(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects(static::once())
            ->method('invalidateExpired')
            ->willThrowException(new \Exception());

        $handler = new InvalidateCacheTaskHandler(
            $this->createMock(EntityRepository::class),
            $this->createMock(LoggerInterface::class),
            $cacheInvalidator,
            0
        );
        $handler->run();
    }
}
