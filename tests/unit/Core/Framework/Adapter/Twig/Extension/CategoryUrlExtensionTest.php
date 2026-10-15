<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Framework\Adapter\Twig\Extension\CategoryUrlExtension;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.9.0 - Will be removed
 */
#[Package('framework')]
#[CoversClass(CategoryUrlExtension::class)]
class CategoryUrlExtensionTest extends TestCase
{
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

    #[DataProvider('linkNewTabProvider')]
    public function testIsLinkNewTabUsesCategorySetting(bool $linkNewTab): void
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->setTranslated(['linkNewTab' => $linkNewTab]);

        $extension = new CategoryUrlExtension(
            new RoutingExtension(static::createStub(UrlGeneratorInterface::class)),
            static::createStub(AbstractCategoryUrlGenerator::class)
        );

        static::assertSame($linkNewTab, $extension->isLinkNewTab($category));
    }

    /**
     * @return iterable<string, array{bool}>
     */
    public static function linkNewTabProvider(): iterable
    {
        yield 'opens link in a new tab' => [true];
        yield 'opens link in the same tab' => [false];
    }
}
