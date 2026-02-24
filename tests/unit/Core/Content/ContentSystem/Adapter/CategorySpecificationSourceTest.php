<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\CategorySpecificationSource;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\CategoryContentLayout\CategoryContentLayoutDefinition;
use Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CategorySpecificationSource::class)]
class CategorySpecificationSourceTest extends TestCase
{
    private EntityLayoutContextFactory&Stub $contextFactory;

    private CategorySpecificationSource $source;

    protected function setUp(): void
    {
        $repository = new StaticEntityRepository([]);
        $definition = static::createStub(CategoryContentLayoutDefinition::class);
        $this->contextFactory = static::createStub(EntityLayoutContextFactory::class);

        $this->source = new CategorySpecificationSource($repository, $definition, $this->contextFactory);
    }

    #[TestDox('delegates supports to context factory')]
    public function testSupportsDelegatesToContextFactory(): void
    {
        $this->contextFactory->method('supports')
            ->willReturn(true);

        $context = Generator::generateSalesChannelContext();

        static::assertTrue($this->source->supports('/category/abc', new Request(), $context));
    }

    #[TestDox('delegates resolveLayoutId to context factory')]
    public function testResolveLayoutIdDelegatesToContextFactory(): void
    {
        $this->contextFactory->method('resolveLayoutId')
            ->willReturn('layout-2');

        $context = Generator::generateSalesChannelContext();

        static::assertSame('layout-2', $this->source->resolveLayoutId('/category/abc', new Request(), $context));
    }

    #[TestDox('throws DecorationPatternException from getDecorated')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        static::expectExceptionObject(new DecorationPatternException(CategorySpecificationSource::class));

        $this->source->getDecorated();
    }
}
