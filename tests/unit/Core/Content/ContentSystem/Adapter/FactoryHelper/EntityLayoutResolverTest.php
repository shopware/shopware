<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\HeaderContentLayout\HeaderContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\HeaderContentLayout\HeaderContentLayoutEntity;
use Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Shopware\Core\Content\ContentSystem\Adapter\ParameterBinding\ParameterBinding;
use Shopware\Core\Content\ContentSystem\ContentSystemException;
use Shopware\Core\Content\ContentSystem\Helper\RequestDataExtractor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(EntityLayoutResolver::class)]
class EntityLayoutResolverTest extends TestCase
{
    private EntityLayoutResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new EntityLayoutResolver(new RequestDataExtractor());
    }

    #[TestDox('returns layout resolution result with assignment and placeholders')]
    public function testResolve(): void
    {
        $entityId = Uuid::randomHex();
        $layoutId = Uuid::randomHex();
        $entity = $this->createAssignmentEntity($layoutId);

        $repository = $this->createRepository($entity);

        $definition = $this->createDefinitionMock('product', 'productId');
        $context = Generator::generateSalesChannelContext();

        $result = $this->resolver->resolve($entityId, new Request(), $context, $repository, $definition);

        static::assertSame($entity, $result->assignment);
        static::assertSame($entityId, $result->placeholderValues->all()['productId']);
    }

    #[TestDox('throws layout assignment not found when no assignment exists')]
    public function testResolveThrowsWhenNoAssignment(): void
    {
        $entityId = Uuid::randomHex();

        $repository = $this->createRepository();

        $definition = $this->createDefinitionMock('product', 'productId');
        $context = Generator::generateSalesChannelContext();

        static::expectExceptionObject(ContentSystemException::layoutAssignmentNotFound(
            'product',
            $entityId,
            $context->getSalesChannel()->getId()
        ));

        $this->resolver->resolve($entityId, new Request(), $context, $repository, $definition);
    }

    #[TestDox('findLayoutId returns layout ID when assignment exists')]
    public function testFindLayoutIdReturnsLayoutIdWhenAssignmentExists(): void
    {
        $layoutId = Uuid::randomHex();
        $entity = $this->createAssignmentEntity($layoutId);

        $repository = $this->createRepository($entity);

        $context = Generator::generateSalesChannelContext();

        $result = $this->resolver->findLayoutId('productId', Uuid::randomHex(), $context, $repository);

        static::assertSame($layoutId, $result);
    }

    #[TestDox('remaps entity ID placeholder when binding exists')]
    public function testBuildPlaceholderValues(): void
    {
        $entityId = Uuid::randomHex();
        $layoutId = Uuid::randomHex();

        $entity = $this->createAssignmentEntity($layoutId, [
            'productId' => new ParameterBinding('productId', 'product_id'),
        ]);

        $repository = $this->createRepository($entity);

        $definition = $this->createDefinitionMock('product', 'productId');
        $context = Generator::generateSalesChannelContext();

        $result = $this->resolver->resolve($entityId, new Request(), $context, $repository, $definition);

        static::assertSame($entityId, $result->placeholderValues->all()['product_id']);
        static::assertArrayNotHasKey('productId', $result->placeholderValues->all());
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

    /**
     * @param array<string, ParameterBinding>|null $bindings
     */
    private function createAssignmentEntity(string $layoutId, ?array $bindings = null): HeaderContentLayoutEntity
    {
        $entity = new HeaderContentLayoutEntity();
        $entity->setId(Uuid::randomHex());
        $entity->setContentLayoutId($layoutId);
        $entity->setParameterBindings($bindings);

        return $entity;
    }

    private function createDefinitionMock(string $entityType, string $entityIdField): AbstractContentLayoutAssignableDefinition&Stub
    {
        $definition = static::createStub(AbstractContentLayoutAssignableDefinition::class);
        $definition->method('getContentLayoutEntityType')->willReturn($entityType);
        $definition->method('getContentLayoutEntityIdField')->willReturn($entityIdField);

        return $definition;
    }
}
