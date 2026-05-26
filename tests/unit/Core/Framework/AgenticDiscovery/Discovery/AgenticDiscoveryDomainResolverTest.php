<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AgenticDiscovery\Discovery;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AgenticDiscovery\Discovery\AgenticDiscoveryDomainResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AgenticDiscoveryDomainResolver::class)]
class AgenticDiscoveryDomainResolverTest extends TestCase
{
    public function testResolvesExactSchemeHostMatchOnFirstQuery(): void
    {
        $domain = $this->makeDomain('https://shop.acme.test');
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())
            ->method('search')
            ->willReturn($this->makeResult([$domain]));

        $resolver = new AgenticDiscoveryDomainResolver($repository);
        $resolved = $resolver->resolve(Request::create('https://shop.acme.test/agents.md'), Context::createDefaultContext());

        static::assertSame($domain, $resolved);
    }

    public function testFallsBackToHostOnlyMatchWhenExactUrlMisses(): void
    {
        $domain = $this->makeDomain('https://shop.acme.test/de');
        $repository = $this->createMock(EntityRepository::class);

        // Two calls: exact match (empty) and the fallback host scan.
        $repository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->makeResult([]),
                $this->makeResult([$domain]),
            );

        $resolver = new AgenticDiscoveryDomainResolver($repository);
        $resolved = $resolver->resolve(Request::create('https://shop.acme.test/agents.md'), Context::createDefaultContext());

        static::assertSame($domain, $resolved);
    }

    public function testReturnsNullWhenNeitherExactNorHostMatch(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('search')->willReturn($this->makeResult([]));

        $resolver = new AgenticDiscoveryDomainResolver($repository);
        static::assertNull(
            $resolver->resolve(Request::create('https://unknown.example/agents.md'), Context::createDefaultContext())
        );
    }

    private function makeDomain(string $url): SalesChannelDomainEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setUniqueIdentifier(Uuid::randomHex());
        $domain->setUrl($url);
        $domain->setSalesChannelId(Uuid::randomHex());

        return $domain;
    }

    /**
     * @param list<SalesChannelDomainEntity> $entities
     *
     * @return EntitySearchResult<SalesChannelDomainCollection>
     */
    private function makeResult(array $entities): EntitySearchResult
    {
        $collection = new SalesChannelDomainCollection($entities);

        return new EntitySearchResult(
            'sales_channel_domain',
            \count($entities),
            $collection,
            null,
            new Criteria(),
            Context::createDefaultContext(),
        );
    }
}
