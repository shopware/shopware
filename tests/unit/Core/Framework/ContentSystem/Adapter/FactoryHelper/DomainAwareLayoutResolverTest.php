<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutCollection;
use Shopware\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutEntity;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DomainAwareLayoutResolver::class)]
class DomainAwareLayoutResolverTest extends TestCase
{
    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

    #[TestDox('returns assignment when domain ID is known and repository returns entity')]
    public function testReturnsAssignmentWhenDomainIsKnown(): void
    {
        $entity = new HeaderContentLayoutEntity();
        $entity->setId($this->ids->get('assignment'));
        $entity->setContentLayoutId($this->ids->get('layout'));

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
        $entity->setId($this->ids->get('assignment'));
        $entity->setContentLayoutId($this->ids->get('layout'));

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($this->ids->get('salesChannel'));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getDomainId')->willReturn(null);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

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

    #[TestDox('returns most specific assignment when multiple candidates exist')]
    public function testReturnsMostSpecificAssignment(): void
    {
        $context = Generator::generateSalesChannelContext();

        $domainSpecific = new HeaderContentLayoutEntity();
        $domainSpecific->setId($this->ids->get('domainAssignment'));
        $domainSpecific->setContentLayoutId('layout-domain');
        $domainSpecific->setDomainId($context->getDomainId());
        $domainSpecific->setSalesChannelId($context->getSalesChannel()->getId());

        $channelOnly = new HeaderContentLayoutEntity();
        $channelOnly->setId($this->ids->get('channelAssignment'));
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
