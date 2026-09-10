<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Framework\Twig\Extension\AnalyticsCategoryPathExtension;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AnalyticsCategoryPathExtension::class)]
class AnalyticsCategoryPathExtensionTest extends TestCase
{
    private AnalyticsCategoryPathExtension $extension;

    private SalesChannelContext $context;

    private string $navigationId;

    protected function setUp(): void
    {
        $this->extension = new AnalyticsCategoryPathExtension();
        $this->navigationId = Uuid::randomHex();

        $this->context = Generator::generateSalesChannelContext();
        $this->context->getSalesChannel()->setNavigationCategoryId($this->navigationId);
    }

    public function testRegistersTheTwigFunction(): void
    {
        $names = array_map(static fn ($function) => $function->getName(), $this->extension->getFunctions());

        static::assertSame(['sw_analytics_category_path'], $names);
    }

    public function testResolvesTheCategoryPathOfAProduct(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setCategories(new CategoryCollection([$this->category(['Clothing', 'Shirts'])]));
        $product->setCategoryIds(array_map(
            static fn (CategoryEntity $category) => $category->getId(),
            $product->getCategories()?->getElements() ?? []
        ));

        static::assertSame(['Clothing', 'Shirts'], $this->extension->getPath($product, $this->context));
    }

    /**
     * A page that does not load the category associations still renders product boxes, so the path
     * has to stay empty instead of failing.
     */
    public function testPathIsEmptyWithoutLoadedAssociations(): void
    {
        static::assertSame([], $this->extension->getPath(new SalesChannelProductEntity(), $this->context));
    }

    public function testPathIsEmptyWithoutAProduct(): void
    {
        static::assertSame([], $this->extension->getPath(null, $this->context));
    }

    /**
     * Partial listing loading can deliver something other than a sales channel product, and the
     * resolver only knows how to read the latter.
     */
    public function testPathIsEmptyForAnotherEntity(): void
    {
        static::assertSame([], $this->extension->getPath(new ProductEntity(), $this->context));
    }

    /**
     * @param list<string> $names the path below the navigation root, the category itself last
     */
    private function category(array $names): CategoryEntity
    {
        $ancestors = [$this->navigationId];
        foreach (\array_slice($names, 0, -1) as $name) {
            $ancestors[] = Uuid::randomHex();
        }

        $id = Uuid::randomHex();

        $category = new CategoryEntity();
        $category->setId($id);
        $category->setUniqueIdentifier($id);
        $category->setActive(true);
        $category->setVisible(true);
        $category->setLevel(\count($names) + 1);
        $category->setPath('|' . implode('|', $ancestors) . '|');
        $category->setTranslated([
            'breadcrumb' => array_combine([...$ancestors, $id], ['Root', ...$names]),
        ]);

        return $category;
    }
}
