<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Adapter\FactoryHelper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\AbstractContentLayoutAssignableDefinition;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\HeaderContentLayout\HeaderContentLayoutCollection;
use Shopware\Core\Framework\ContentSystem\Adapter\Entity\HeaderContentLayout\HeaderContentLayoutEntity;
use Shopware\Core\Framework\ContentSystem\Adapter\FactoryHelper\EntityLayoutResolver;
use Shopware\Core\Framework\ContentSystem\Adapter\ParameterBinding\ParameterBinding;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Helper\RequestDataExtractor;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
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
        $this->resolver = new EntityLayoutResolver(new RequestDataExtractor());
    }

    #[TestDox('returns layout resolution result with assignment and placeholders')]
    public function testReturnsResolutionResultWithAssignmentAndPlaceholders(): void
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

    #[TestDox('remaps entity ID placeholder when binding exists')]
    public function testRemapsEntityIdPlaceholderWhenBindingExists(): void
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

    #[TestDox('throws layout assignment not found when no assignment exists')]
    public function testThrowsWhenNoAssignment(): void
    {
        $entityId = Uuid::randomHex();

        $repository = $this->createRepository();

        $definition = $this->createDefinitionMock('product', 'productId');
        $context = Generator::generateSalesChannelContext();

        $this->expectExceptionObject(ContentSystemException::layoutAssignmentNotFound(
            'product',
            $entityId,
            $context->getSalesChannel()->getId()
        ));

        $this->resolver->resolve($entityId, new Request(), $context, $repository, $definition);
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
