<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutCollection;
use Shopware\Storefront\ContentSystem\HeaderContentLayout\HeaderContentLayoutEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(EntityLayoutResolver::class)]
class EntityLayoutResolverTest extends TestCase
{
    private EntityLayoutResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new EntityLayoutResolver();
    }

    #[TestDox('builds placeholder values from entity id field and scalar query parameters, ignoring non-scalar parameters')]
    public function testResolvePlaceholdersMergesEntityIdWithScalarQueryParameters(): void
    {
        $request = new Request(['color' => 'red', 'tags' => ['a', 'b']]);

        $result = $this->resolver->resolvePlaceholders('productId', 'product-id-1', $request);

        static::assertSame(
            ['productId' => 'product-id-1', 'color' => 'red'],
            $result->all()
        );
    }

    #[TestDox('returns layout ID when assignment exists')]
    public function testReturnsLayoutIdWhenAssignmentExists(): void
    {
        $layoutId = Uuid::randomHex();
        $entity = $this->createAssignmentEntity($layoutId);

        $repository = $this->createRepository($entity);

        $context = Generator::generateSalesChannelContext();

        $result = $this->resolver->findLayoutId('productId', Uuid::randomHex(), $context, $repository);

        static::assertSame($layoutId, $result);
    }

    #[TestDox('returns null when no assignment exists')]
    public function testReturnsNullFromLayoutIdLookupWhenNoAssignment(): void
    {
        $repository = $this->createRepository();
        $context = Generator::generateSalesChannelContext();

        $result = $this->resolver->findLayoutId('productId', Uuid::randomHex(), $context, $repository);

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

    private function createAssignmentEntity(string $layoutId): HeaderContentLayoutEntity
    {
        $entity = new HeaderContentLayoutEntity();
        $entity->setId(Uuid::randomHex());
        $entity->setContentLayoutId($layoutId);

        return $entity;
    }
}
