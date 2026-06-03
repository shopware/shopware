<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Framework\Adapter\Twig\Extension\CategoryUrlExtension;
use Shopware\Core\Framework\Context;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Symfony\Bridge\Twig\Extension\RoutingExtension;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @internal
 *
 * @deprecated tag:v6.8.0 - Will be removed
 */
#[CoversClass(CategoryUrlExtension::class)]
class CategoryUrlExtensionTest extends TestCase
{
    #[DisabledFeatures(['v6.8.0.0'])]
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
            new RoutingExtension($this->createMock(UrlGeneratorInterface::class)),
            $categoryUrlGenerator
        );

        static::assertSame('/navigation', $extension->getCategoryUrl([
            'context' => Context::createDefaultContext(),
            'salesChannelContext' => $salesChannelContext,
        ], $category));
    }
}
