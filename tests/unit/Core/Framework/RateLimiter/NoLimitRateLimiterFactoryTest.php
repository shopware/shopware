<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\RateLimiter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\NoLimitRateLimiterFactory;
use Shopware\Core\Framework\RateLimiter\RateLimiterFactory;
use Shopware\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Symfony\Component\RateLimiter\Policy\NoLimiter;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NoLimitRateLimiterFactory::class)]
class NoLimitRateLimiterFactoryTest extends TestCase
{
    public function testCreateForwardsKeyAndSalesChannelIdToDecoratedFactory(): void
    {
        DisableRateLimiterCompilerPass::disableNoLimit();

        try {
            $inner = $this->createMock(RateLimiterFactory::class);
            $inner
                ->expects($this->once())
                ->method('create')
                ->with('some-key', 'sales-channel-id')
                ->willReturn(new NoLimiter());

            $factory = new NoLimitRateLimiterFactory($inner);
            $factory->create('some-key', 'sales-channel-id');
        } finally {
            DisableRateLimiterCompilerPass::enableNoLimit();
        }
    }

    public function testCreateReturnsNoLimiterWhenRateLimitingIsDisabled(): void
    {
        DisableRateLimiterCompilerPass::enableNoLimit();

        $inner = $this->createMock(RateLimiterFactory::class);
        $inner->expects($this->never())->method('create');

        $factory = new NoLimitRateLimiterFactory($inner);
        static::assertInstanceOf(NoLimiter::class, $factory->create('some-key', 'sales-channel-id'));
    }
}
