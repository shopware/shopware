<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Framework\Adapter\Twig\Extension\CategoryUrlExtension;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CategoryUrlExtension::class)]
class CategoryUrlExtensionTest extends TestCase
{
    public function testTwigFunctionsAreMarkedDeprecated(): void
    {
        $extension = new CategoryUrlExtension(
            new RoutingExtension(static::createStub(UrlGeneratorInterface::class)),
            static::createStub(AbstractCategoryUrlGenerator::class)
        );

        $functions = [];
        foreach ($extension->getFunctions() as $function) {
            $functions[$function->getName()] = $function;
        }

        static::assertArrayHasKey('category_url', $functions);
        static::assertArrayHasKey('category_linknewtab', $functions);
        static::assertTrue($functions['category_url']->isDeprecated());
        static::assertTrue($functions['category_linknewtab']->isDeprecated());
    }

    public function testGetCategoryUrlReturnsSeoUrlForSalesChannelCategory(): void
    {
        $categoryUrlGenerator = $this->createMock(AbstractCategoryUrlGenerator::class);
        $categoryUrlGenerator->expects($this->never())->method('generate');

        $extension = new CategoryUrlExtension(
            new RoutingExtension(static::createStub(UrlGeneratorInterface::class)),
            $categoryUrlGenerator
        );
        $category = new SalesChannelCategoryEntity();
        $category->setSeoUrl('/category');

        static::assertSame('/category', $extension->getCategoryUrl([], $category));
    }

    public function testGetCategoryUrlUsesSalesChannelContextFallback(): void
    {
        $category = new CategoryEntity();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $categoryUrlGenerator = $this->createMock(AbstractCategoryUrlGenerator::class);
        $categoryUrlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with($category, $salesChannelContext->getSalesChannel())
            ->willReturn('/navigation');

        $extension = new CategoryUrlExtension(
            new RoutingExtension(static::createStub(UrlGeneratorInterface::class)),
            $categoryUrlGenerator
        );

        static::assertSame('/navigation', $extension->getCategoryUrl([
            'context' => Context::createDefaultContext(),
            'salesChannelContext' => $salesChannelContext,
        ], $category));
    }
}
