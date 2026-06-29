<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\Service\AbstractCategoryUrlGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Seo\StorefrontCategoryUrlGenerator;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorefrontCategoryUrlGenerator::class)]
class StorefrontCategoryUrlGeneratorTest extends TestCase
{
    public function testGenerateHomePageLinkUsesRouter(): void
    {
        $navigationCategoryId = Uuid::randomHex();

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with('frontend.home.page')
            ->willReturn('/');

        $decorated = $this->createMock(AbstractCategoryUrlGenerator::class);
        $decorated->expects($this->never())->method('generate');

        $generator = new StorefrontCategoryUrlGenerator($decorated, $router);

        $category = $this->createCategoryLink($navigationCategoryId);

        static::assertSame('/', $generator->generate($category, $this->createSalesChannel($navigationCategoryId)));
    }

    public function testGenerateDelegatesWhenCategoryIsNotALink(): void
    {
        $navigationCategoryId = Uuid::randomHex();

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->never())->method('generate');

        $decorated = $this->createMock(AbstractCategoryUrlGenerator::class);
        $decorated->expects($this->once())
            ->method('generate')
            ->willReturn('DELEGATED');

        $generator = new StorefrontCategoryUrlGenerator($decorated, $router);

        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_PAGE);
        $category->setId($navigationCategoryId);

        static::assertSame('DELEGATED', $generator->generate($category, $this->createSalesChannel($navigationCategoryId)));
    }

    public function testGenerateDelegatesForNonCategoryLinkType(): void
    {
        $internalLink = Uuid::randomHex();

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->never())->method('generate');

        $decorated = $this->createMock(AbstractCategoryUrlGenerator::class);
        $decorated->expects($this->once())
            ->method('generate')
            ->willReturn('DELEGATED');

        $generator = new StorefrontCategoryUrlGenerator($decorated, $router);

        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->addTranslated('linkType', CategoryDefinition::LINK_TYPE_PRODUCT);
        $category->addTranslated('internalLink', $internalLink);

        static::assertSame('DELEGATED', $generator->generate($category, $this->createSalesChannel($internalLink)));
    }

    public function testGetDecoratedReturnsInner(): void
    {
        $decorated = $this->createMock(AbstractCategoryUrlGenerator::class);
        $generator = new StorefrontCategoryUrlGenerator($decorated, $this->createMock(RouterInterface::class));

        static::assertSame($decorated, $generator->getDecorated());
    }

    private function createCategoryLink(string $internalLink): CategoryEntity
    {
        $category = new CategoryEntity();
        $category->setType(CategoryDefinition::TYPE_LINK);
        $category->addTranslated('linkType', CategoryDefinition::LINK_TYPE_CATEGORY);
        $category->addTranslated('internalLink', $internalLink);

        return $category;
    }

    private function createSalesChannel(string $navigationCategoryId): SalesChannelEntity
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setNavigationCategoryId($navigationCategoryId);

        return $salesChannel;
    }
}
