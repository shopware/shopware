<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Capability\Loyalty;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Capability\Loyalty\LoyaltyAggregator;
use Shopware\Core\Framework\Ucp\Capability\Loyalty\LoyaltyProviderInterface;
use Shopware\Core\Framework\Ucp\UcpException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * Multi-provider loyalty aggregation. Verifies that the namespace uniqueness
 * invariant is enforced (the spec mandates exactly one provider per namespace)
 * and that null returns from providers are silently dropped.
 *
 * @internal
 */
#[CoversClass(LoyaltyAggregator::class)]
class LoyaltyAggregatorTest extends TestCase
{
    public function testReturnsEmptyWhenNoProvidersRegistered(): void
    {
        $aggregator = new LoyaltyAggregator([]);
        $result = $aggregator->aggregate($this->createMock(SalesChannelContext::class));

        static::assertSame([], $result);
    }

    public function testAggregatesSingleProvider(): void
    {
        $provider = $this->makeProvider('com.acme.loyalty', [
            'tier' => 'gold',
            'balance' => 1250,
        ]);

        $result = (new LoyaltyAggregator([$provider]))->aggregate($this->createMock(SalesChannelContext::class));

        static::assertCount(1, $result);
        static::assertSame('com.acme.loyalty', $result[0]['namespace']);
        static::assertSame('gold', $result[0]['tier']);
        static::assertSame(1250, $result[0]['balance']);
    }

    public function testIgnoresProvidersThatReturnNull(): void
    {
        $silentProvider = $this->createMock(LoyaltyProviderInterface::class);
        $silentProvider->method('getMembershipNamespace')->willReturn('com.silent.loyalty');
        $silentProvider->method('buildLoyaltyData')->willReturn(null);

        $activeProvider = $this->makeProvider('com.acme.loyalty', ['balance' => 100]);

        $result = (new LoyaltyAggregator([$silentProvider, $activeProvider]))
            ->aggregate($this->createMock(SalesChannelContext::class));

        static::assertCount(1, $result);
        static::assertSame('com.acme.loyalty', $result[0]['namespace']);
    }

    public function testEnforcesNamespaceUniquenessAcrossProviders(): void
    {
        $a = $this->makeProvider('com.acme.loyalty', ['balance' => 10]);
        $b = $this->makeProvider('com.acme.loyalty', ['balance' => 20]);

        $this->expectExceptionObject(UcpException::loyaltyProviderError(
            'multiple providers claim namespace "com.acme.loyalty" — each membership namespace must have exactly one provider'
        ));

        (new LoyaltyAggregator([$a, $b]))->aggregate($this->createMock(SalesChannelContext::class));
    }

    public function testEnforcesNamespaceFieldOnEmittedEntry(): void
    {
        // Provider tries to drop the namespace field — aggregator must restore it.
        $provider = $this->makeProvider('com.acme.loyalty', ['balance' => 10]); // namespace not in returned data
        $result = (new LoyaltyAggregator([$provider]))->aggregate($this->createMock(SalesChannelContext::class));

        static::assertSame('com.acme.loyalty', $result[0]['namespace']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function makeProvider(string $namespace, array $data): LoyaltyProviderInterface
    {
        $provider = $this->createMock(LoyaltyProviderInterface::class);
        $provider->method('getMembershipNamespace')->willReturn($namespace);
        $provider->method('buildLoyaltyData')->willReturn($data);

        return $provider;
    }
}
