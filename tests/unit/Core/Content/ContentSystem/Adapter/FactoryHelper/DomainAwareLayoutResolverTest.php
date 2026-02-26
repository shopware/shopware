<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\HeaderContentLayout\HeaderContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\HeaderContentLayout\HeaderContentLayoutEntity;
use Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(DomainAwareLayoutResolver::class)]
class DomainAwareLayoutResolverTest extends TestCase
{
    #[TestDox('returns assignment when domain ID is known and repository returns entity')]
    public function testReturnsAssignmentWhenDomainIsKnown(): void
    {
        $entity = new HeaderContentLayoutEntity();
        $entity->setId(Uuid::randomHex());
        $entity->setContentLayoutId(Uuid::randomHex());

        $context = Generator::generateSalesChannelContext();

        /** @var StaticEntityRepository<HeaderContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) use ($entity): array {
                $filters = $criteria->getFilters();
                static::assertCount(1, $filters);
                static::assertInstanceOf(OrFilter::class, $filters[0]);

                static::assertSame(1, $criteria->getLimit());
                static::assertContains('contentLayout', array_keys($criteria->getAssociations()));

                return [$entity];
            },
        ]);

        $resolver = new DomainAwareLayoutResolver();
        $result = $resolver->resolve($context, $repository);

        static::assertSame($entity, $result);
    }

    #[TestDox('returns assignment when domain ID is null using salesChannel-only filter')]
    public function testReturnsAssignmentWhenDomainIsNull(): void
    {
        $entity = new HeaderContentLayoutEntity();
        $entity->setId(Uuid::randomHex());
        $entity->setContentLayoutId(Uuid::randomHex());

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getDomainId')->willReturn(null);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        /** @var StaticEntityRepository<HeaderContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) use ($entity): array {
                $filters = $criteria->getFilters();
                static::assertCount(2, $filters);
                static::assertInstanceOf(EqualsFilter::class, $filters[0]);
                static::assertSame('domainId', $filters[0]->getField());
                static::assertNull($filters[0]->getValue());
                static::assertInstanceOf(OrFilter::class, $filters[1]);

                return [$entity];
            },
        ]);

        $resolver = new DomainAwareLayoutResolver();
        $result = $resolver->resolve($context, $repository);

        static::assertSame($entity, $result);
    }

    #[TestDox('returns most specific assignment when multiple candidates exist')]
    public function testReturnsMostSpecificAssignment(): void
    {
        $context = Generator::generateSalesChannelContext();

        $domainSpecific = new HeaderContentLayoutEntity();
        $domainSpecific->setId(Uuid::randomHex());
        $domainSpecific->setContentLayoutId('layout-domain');
        $domainSpecific->setDomainId($context->getDomainId());
        $domainSpecific->setSalesChannelId($context->getSalesChannel()->getId());

        $channelOnly = new HeaderContentLayoutEntity();
        $channelOnly->setId(Uuid::randomHex());
        $channelOnly->setContentLayoutId('layout-channel');
        $channelOnly->setSalesChannelId($context->getSalesChannel()->getId());

        $repository = $this->createRepository($domainSpecific, $channelOnly);

        $resolver = new DomainAwareLayoutResolver();
        $result = $resolver->resolve($context, $repository);

        static::assertSame($domainSpecific, $result);
    }

    #[TestDox('returns null when no assignment exists')]
    public function testReturnsNullWhenNoAssignmentExists(): void
    {
        $repository = $this->createRepository();

        $context = Generator::generateSalesChannelContext();

        $resolver = new DomainAwareLayoutResolver();
        $result = $resolver->resolve($context, $repository);

        static::assertNull($result);
    }

    /**
     * @return StaticEntityRepository<HeaderContentLayoutCollection>
     */
    private function createRepository(HeaderContentLayoutEntity ...$entities): StaticEntityRepository
    {
        /** @var StaticEntityRepository<HeaderContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([$entities]);

        return $repository;
    }
}
