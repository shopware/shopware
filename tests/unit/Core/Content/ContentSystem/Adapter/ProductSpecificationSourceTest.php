<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ProductContentLayout\ProductContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\ProductContentLayout\ProductContentLayoutDefinition;
use Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopware\Core\Content\ContentSystem\Adapter\ProductSpecificationSource;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductSpecificationSource::class)]
class ProductSpecificationSourceTest extends TestCase
{
    private EntityLayoutContextFactory&Stub $contextFactory;

    private ProductSpecificationSource $source;

    protected function setUp(): void
    {
        /** @var StaticEntityRepository<ProductContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([]);
        $definition = static::createStub(ProductContentLayoutDefinition::class);
        $this->contextFactory = static::createStub(EntityLayoutContextFactory::class);

        $this->source = new ProductSpecificationSource($repository, $definition, $this->contextFactory);
    }

    #[TestDox('delegates supports to context factory')]
    public function testSupportsDelegatesToContextFactory(): void
    {
        $this->contextFactory->method('supports')
            ->willReturn(true);

        $context = Generator::generateSalesChannelContext();

        static::assertTrue($this->source->supports('/product/abc', new Request(), $context));
    }

    #[TestDox('delegates resolveLayoutId to context factory')]
    public function testResolveLayoutIdDelegatesToContextFactory(): void
    {
        $this->contextFactory->method('resolveLayoutId')
            ->willReturn('layout-1');

        $context = Generator::generateSalesChannelContext();

        static::assertSame('layout-1', $this->source->resolveLayoutId('/product/abc', new Request(), $context));
    }

    #[TestDox('throws DecorationPatternException from getDecorated')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        static::expectExceptionObject(new DecorationPatternException(ProductSpecificationSource::class));

        $this->source->getDecorated();
    }
}
