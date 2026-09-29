<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheTaskHandler;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InvalidateCacheTaskHandler::class)]
class InvalidateCacheTaskHandlerTest extends TestCase
{
    public function testRunWithoutDelay(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('invalidateExpired');

        $handler = new InvalidateCacheTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $cacheInvalidator
        );
        $handler->run();
    }

    public function testRunWithDelay(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())->method('invalidateExpired');

        $handler = new InvalidateCacheTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $cacheInvalidator
        );
        $handler->run();
    }

    public function testRunDoesCatchException(): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator->expects($this->once())
            ->method('invalidateExpired')
            ->willThrowException(new \Exception());

        $handler = new InvalidateCacheTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $cacheInvalidator
        );
        $handler->run();
    }
}
