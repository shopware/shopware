<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Adapter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\LandingPageContentLayout\LandingPageContentLayoutCollection;
use Shopware\Core\Content\ContentSystem\Adapter\Entity\LandingPageContentLayout\LandingPageContentLayoutDefinition;
use Shopware\Core\Content\ContentSystem\Adapter\FactoryHelper\EntityLayoutContextFactory;
use Shopware\Core\Content\ContentSystem\Adapter\LandingPageSpecificationSource;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LandingPageSpecificationSource::class)]
class LandingPageSpecificationSourceTest extends TestCase
{
    private EntityLayoutContextFactory&Stub $contextFactory;

    private LandingPageSpecificationSource $source;

    protected function setUp(): void
    {
        /** @var StaticEntityRepository<LandingPageContentLayoutCollection> $repository */
        $repository = new StaticEntityRepository([]);
        $definition = static::createStub(LandingPageContentLayoutDefinition::class);
        $this->contextFactory = static::createStub(EntityLayoutContextFactory::class);

        $this->source = new LandingPageSpecificationSource($repository, $definition, $this->contextFactory);
    }

    #[TestDox('delegates supports to context factory')]
    public function testSupportsDelegatesToContextFactory(): void
    {
        $this->contextFactory->method('supports')
            ->willReturn(true);

        $context = Generator::generateSalesChannelContext();

        static::assertTrue($this->source->supports('/landing-page/abc', new Request(), $context));
    }

    #[TestDox('delegates resolveLayoutId to context factory')]
    public function testResolveLayoutIdDelegatesToContextFactory(): void
    {
        $this->contextFactory->method('resolveLayoutId')
            ->willReturn('layout-3');

        $context = Generator::generateSalesChannelContext();

        static::assertSame('layout-3', $this->source->resolveLayoutId('/landing-page/abc', new Request(), $context));
    }

    #[TestDox('throws DecorationPatternException from getDecorated')]
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        static::expectExceptionObject(new DecorationPatternException(LandingPageSpecificationSource::class));

        $this->source->getDecorated();
    }
}
