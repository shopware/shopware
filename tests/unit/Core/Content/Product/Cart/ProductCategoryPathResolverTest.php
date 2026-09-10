<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Cart\ProductCategoryPathResolver;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryCollection;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductCategoryPathResolver::class)]
class ProductCategoryPathResolverTest extends TestCase
{
    private ProductCategoryPathResolver $resolver;

    private IdsCollection $ids;

    private SalesChannelContext $context;

    private string $navigationId;

    protected function setUp(): void
    {
        $this->resolver = new ProductCategoryPathResolver();
        $this->ids = new IdsCollection();
        $this->navigationId = $this->ids->get('navigation');

        // the service and footer category ids default to null, so the navigation root is the
        // only entry point unless a test sets another one
        $this->context = Generator::generateSalesChannelContext();
        $this->context->getSalesChannel()->setNavigationCategoryId($this->navigationId);
    }

    public function testPathIsEmptyWithoutCategories(): void
    {
        $product = new SalesChannelProductEntity();
        $product->setCategoryIds([]);

        static::assertSame([], $this->resolver->getPath($product, $this->context));
    }

    public function testPathIsEmptyWhenTheProductHasNoVisibleCategory(): void
    {
        $hidden = $this->category('hidden', ['Mid', 'Hidden'], visible: false);

        static::assertSame([], $this->resolver->getPath($this->product([$hidden]), $this->context));
    }

    public function testPathStartsBelowTheNavigationRoot(): void
    {
        $leaf = $this->category('leaf', ['Mid', 'Leaf']);

        static::assertSame(['Mid', 'Leaf'], $this->resolver->getPath($this->product([$leaf]), $this->context));
    }

    public function testPathUsesTheDeepestCategoryForMultiBranchProducts(): void
    {
        $shallow = $this->category('shallow', ['Other']);
        $deep = $this->category('deep', ['Mid', 'Deeper', 'Deepest']);

        static::assertSame(
            ['Mid', 'Deeper', 'Deepest'],
            $this->resolver->getPath($this->product([$shallow, $deep]), $this->context)
        );
    }

    public function testPathSkipsInactiveAndInvisibleCategories(): void
    {
        $inactive = $this->category('inactive', ['Mid', 'A', 'Inactive'], active: false);
        $invisible = $this->category('invisible', ['Mid', 'A', 'Invisible'], visible: false);
        $visible = $this->category('visible', ['Visible']);

        static::assertSame(
            ['Visible'],
            $this->resolver->getPath($this->product([$inactive, $invisible, $visible]), $this->context)
        );
    }

    public function testPathIgnoresCategoriesOutsideTheSalesChannelTree(): void
    {
        $foreign = $this->category('foreign', ['Deep', 'Deeper', 'Foreign'], root: Uuid::randomHex());
        $own = $this->category('own', ['Own']);

        static::assertSame(['Own'], $this->resolver->getPath($this->product([$foreign, $own]), $this->context));
    }

    public function testPathResolvesCategoriesBelowTheServiceCategory(): void
    {
        $serviceRootId = $this->ids->get('service-root');
        $this->context->getSalesChannel()->setServiceCategoryId($serviceRootId);

        $category = $this->category('contact', ['Service', 'Contact'], root: $serviceRootId);

        static::assertSame(['Service', 'Contact'], $this->resolver->getPath($this->product([$category]), $this->context));
    }

    public function testPathPrefersTheSalesChannelMainCategory(): void
    {
        $main = $this->category('main', ['MainCat']);
        $deep = $this->category('deep', ['Mid', 'Deeper', 'Deepest']);

        $product = $this->product([$main, $deep], mainCategory: $main);

        static::assertSame(['MainCat'], $this->resolver->getPath($product, $this->context));
    }

    public function testMainCategoryOfAnotherSalesChannelIsIgnored(): void
    {
        $main = $this->category('main', ['MainCat']);
        $deep = $this->category('deep', ['Mid', 'Deepest']);

        $product = $this->product([$main, $deep], mainCategory: $main, mainCategorySalesChannelId: Uuid::randomHex());

        static::assertSame(['Mid', 'Deepest'], $this->resolver->getPath($product, $this->context));
    }

    public function testMainCategoryNotAssignedToTheProductIsIgnored(): void
    {
        $unassigned = $this->category('unassigned', ['Unassigned']);
        $deep = $this->category('deep', ['Mid', 'Deepest']);

        // the main category is only honoured when it is one of the product's own categories
        $product = $this->product([$deep], mainCategory: $unassigned);

        static::assertSame(['Mid', 'Deepest'], $this->resolver->getPath($product, $this->context));
    }

    public function testInvisibleMainCategoryFallsBackToTheDeepestCategory(): void
    {
        $main = $this->category('main', ['MainCat'], visible: false);
        $deep = $this->category('deep', ['Mid', 'Deepest']);

        $product = $this->product([$main, $deep], mainCategory: $main);

        static::assertSame(['Mid', 'Deepest'], $this->resolver->getPath($product, $this->context));
    }

    public function testFullBreadcrumbIsUsedWhenItDoesNotContainAnEntryPoint(): void
    {
        // a category inside the sales channel tree whose stored breadcrumb starts below the root
        $category = $this->category('leaf', ['Mid', 'Leaf']);
        $breadcrumb = $category->getTranslated()['breadcrumb'];
        unset($breadcrumb[$this->navigationId]);
        $category->setTranslated(['breadcrumb' => $breadcrumb]);

        static::assertSame(['Mid', 'Leaf'], $this->resolver->getPath($this->product([$category]), $this->context));
    }

    /**
     * @param list<CategoryEntity> $categories
     */
    private function product(
        array $categories,
        ?CategoryEntity $mainCategory = null,
        ?string $mainCategorySalesChannelId = null,
    ): SalesChannelProductEntity {
        $product = new SalesChannelProductEntity();
        $product->setCategories(new CategoryCollection($categories));
        $product->setCategoryIds(array_map(static fn (CategoryEntity $category) => $category->getId(), $categories));

        if ($mainCategory !== null) {
            $entity = new MainCategoryEntity();
            $entity->setUniqueIdentifier(Uuid::randomHex());
            $entity->setSalesChannelId($mainCategorySalesChannelId ?? $this->context->getSalesChannelId());
            $entity->setCategory($mainCategory);

            $product->setMainCategories(new MainCategoryCollection([$entity]));
        }

        return $product;
    }

    /**
     * Builds a category the way the DAL delivers it: `path` is the pipe delimited list of
     * ancestor ids excluding the category itself, and the translated `breadcrumb` is keyed by
     * category id and includes the tree root.
     *
     * @param list<string> $names the path below the root, the category itself last
     */
    private function category(
        string $key,
        array $names,
        bool $active = true,
        bool $visible = true,
        ?string $root = null,
    ): CategoryEntity {
        $root ??= $this->navigationId;

        $ancestors = [$root];
        foreach (\array_slice($names, 0, -1) as $index => $name) {
            $ancestors[] = $this->ids->get($key . '-ancestor-' . $index);
        }

        $category = new CategoryEntity();
        $category->setId($this->ids->get($key));
        $category->setUniqueIdentifier($this->ids->get($key));
        $category->setActive($active);
        $category->setVisible($visible);
        $category->setLevel(\count($names) + 1);
        $category->setPath('|' . implode('|', $ancestors) . '|');
        $category->setTranslated([
            'breadcrumb' => array_combine([...$ancestors, $category->getId()], ['Root', ...$names]),
        ]);

        return $category;
    }
}
