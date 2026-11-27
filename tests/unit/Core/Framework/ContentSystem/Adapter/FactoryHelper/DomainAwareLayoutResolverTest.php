<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\DomainAwareLayoutResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Tests\Unit\Core\Framework\ContentSystem\Fixture\SectionContentLayoutAssignmentCollection;
use Shopware\Tests\Unit\Core\Framework\ContentSystem\Fixture\SectionContentLayoutAssignmentEntity;

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

    #[DataProvider('domainIdProvider')]
    #[TestDox('returns the assignment the domain-scoped criteria resolves for a known or a null domain ID')]
    public function testReturnsResolvedAssignment(?string $domainId): void
    {
        $entity = new SectionContentLayoutAssignmentEntity();
        $entity->setId($this->ids->get('assignment'));
        $entity->setContentLayoutId($this->ids->get('layout'));

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($this->ids->get('salesChannel'));

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getDomainId')->willReturn($domainId);
        $context->method('getSalesChannel')->willReturn($salesChannel);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $repository = StaticEntityRepository::of(SectionContentLayoutAssignmentCollection::class, [
            static function (Criteria $criteria) use ($entity, $domainId): array {
                $filters = $criteria->getFilters();
                static::assertCount(1, $filters);
                static::assertInstanceOf(OrFilter::class, $filters[0]);

                $domainScoped = $filters[0]->getQueries()[0];
                static::assertInstanceOf(MultiFilter::class, $domainScoped);
                $domainFilter = $domainScoped->getQueries()[0];
                static::assertInstanceOf(EqualsFilter::class, $domainFilter);
                static::assertSame('domainId', $domainFilter->getField());
                static::assertSame($domainId, $domainFilter->getValue());

                static::assertSame(1, $criteria->getLimit());
                static::assertContains('contentLayout', array_keys($criteria->getAssociations()));

                return [$entity];
            },
        ]);

        $result = (new DomainAwareLayoutResolver())->resolve($context, $repository);

        static::assertSame($entity, $result);
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function domainIdProvider(): iterable
    {
        yield 'known domain id' => ['domain-known-id'];
        yield 'null domain id' => [null];
    }

    #[TestDox('returns most specific assignment when multiple candidates exist')]
    public function testReturnsMostSpecificAssignment(): void
    {
        $context = Generator::generateSalesChannelContext();

        $domainSpecific = new SectionContentLayoutAssignmentEntity();
        $domainSpecific->setId($this->ids->get('domainAssignment'));
        $domainSpecific->setContentLayoutId('layout-domain');
        $domainSpecific->setDomainId($context->getDomainId());
        $domainSpecific->setSalesChannelId($context->getSalesChannel()->getId());

        $channelOnly = new SectionContentLayoutAssignmentEntity();
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
     * @return StaticEntityRepository<SectionContentLayoutAssignmentCollection>
     */
    private function createRepository(SectionContentLayoutAssignmentEntity ...$entities): StaticEntityRepository
    {
        $repository = StaticEntityRepository::of(SectionContentLayoutAssignmentCollection::class, [$entities]);

        return $repository;
    }
}
