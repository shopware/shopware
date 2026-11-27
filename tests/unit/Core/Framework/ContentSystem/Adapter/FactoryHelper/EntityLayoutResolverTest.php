<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutCollection;
use Shopware\Core\Content\Category\Aggregate\CategoryContentLayout\CategoryContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityLayoutResolver::class)]
class EntityLayoutResolverTest extends TestCase
{
    private EntityLayoutResolver $resolver;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->resolver = new EntityLayoutResolver();
    }

    #[TestDox('builds the entity id placeholder from the entity id field')]
    public function testResolvePlaceholdersBuildsTheEntityIdPlaceholder(): void
    {
        $result = $this->resolver->resolvePlaceholders('productId', 'product-id-1');

        static::assertSame(['productId' => 'product-id-1'], $result->all());
    }

    #[TestDox('returns layout ID when assignment exists')]
    public function testReturnsLayoutIdWhenAssignmentExists(): void
    {
        $layoutId = $this->ids->get('layout');
        $entity = $this->createAssignmentEntity($layoutId);

        $repository = $this->createRepository($entity);

        $context = Generator::generateSalesChannelContext();

        $result = $this->resolver->findLayoutId('productId', $this->ids->get('product'), $context, $repository);

        static::assertSame($layoutId, $result);
    }

    #[TestDox('returns null when no assignment exists')]
    public function testReturnsNullFromLayoutIdLookupWhenNoAssignment(): void
    {
        $repository = $this->createRepository();
        $context = Generator::generateSalesChannelContext();

        $result = $this->resolver->findLayoutId('productId', $this->ids->get('product'), $context, $repository);

        static::assertNull($result);
    }

    /**
     * @return StaticEntityRepository<CategoryContentLayoutCollection>
     */
    private function createRepository(CategoryContentLayoutEntity ...$entities): StaticEntityRepository
    {
        $repository = StaticEntityRepository::of(CategoryContentLayoutCollection::class, [$entities]);

        return $repository;
    }

    private function createAssignmentEntity(string $layoutId): CategoryContentLayoutEntity
    {
        $entity = new CategoryContentLayoutEntity();
        $entity->setId($this->ids->get('assignment'));
        $entity->setContentLayoutId($layoutId);

        return $entity;
    }
}
