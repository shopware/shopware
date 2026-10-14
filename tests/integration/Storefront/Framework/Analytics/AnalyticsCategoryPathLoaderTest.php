<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Analytics;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Framework\Analytics\AnalyticsCategoryPathLoader;

/**
 * @internal
 */
#[Package('discovery')]
class AnalyticsCategoryPathLoaderTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private IdsCollection $ids;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create($this->ids->create('token'), TestDefaults::SALES_CHANNEL);

        $navigationId = $this->context->getSalesChannel()->getNavigationCategoryId();

        static::getContainer()->get('category.repository')->create([
            [
                'id' => $this->ids->create('Clothing'),
                'name' => 'Clothing',
                'parentId' => $navigationId,
                'children' => [['id' => $this->ids->create('Shirts'), 'name' => 'Shirts']],
            ],
            // a category outside the navigation of the sales channel, such as one of the footer
            ['id' => $this->ids->create('Footer'), 'name' => 'Footer'],
        ], Context::createDefaultContext());

        $products = [
            (new ProductBuilder($this->ids, 'deepest'))->price(10)->visibility()->category('Clothing')->category('Shirts')
                ->variant((new ProductBuilder($this->ids, 'variant'))->price(10)->build())
                ->build(),
            (new ProductBuilder($this->ids, 'main'))->price(10)->visibility()->category('Shirts')->category('Clothing')
                ->mainCategory(TestDefaults::SALES_CHANNEL, 'Clothing')
                ->build(),
            (new ProductBuilder($this->ids, 'outside'))->price(10)->visibility()->category('Footer')->build(),
        ];

        static::getContainer()->get('product.repository')->create($products, Context::createDefaultContext());

        // the context has to know the tax of the new products, as the main categories are read
        // through the sales channel product repository
        $this->context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create($this->ids->create('token-after-products'), TestDefaults::SALES_CHANNEL);
    }

    public function testReportsTheDeepestAssignedCategoryPathBelowTheNavigation(): void
    {
        $paths = $this->load('deepest');

        static::assertSame([$this->ids->get('deepest') => ['Clothing', 'Shirts']], $paths);
    }

    public function testPrefersTheMainCategoryOfTheSalesChannel(): void
    {
        static::assertSame([$this->ids->get('main') => ['Clothing']], $this->load('main'));
    }

    public function testAVariantUsesTheCategoriesOfItsParent(): void
    {
        static::assertSame([$this->ids->get('variant') => ['Clothing', 'Shirts']], $this->load('variant'));
    }

    public function testReportsNoPathForACategoryOutsideTheNavigation(): void
    {
        static::assertSame([], $this->load('outside'));
    }

    public function testResolvesSeveralProductsTogether(): void
    {
        $paths = $this->load('deepest', 'main', 'outside');

        // keyed by product id, the order does not matter
        static::assertEquals([
            $this->ids->get('deepest') => ['Clothing', 'Shirts'],
            $this->ids->get('main') => ['Clothing'],
        ], $paths);
    }

    public function testExcludesCategoriesBlockedByASalesChannelSubscriber(): void
    {
        $dispatcher = static::getContainer()->get('event_dispatcher');
        $listener = function (SalesChannelEntityLoadedEvent $event): void {
            foreach ($event->getEntities() as $category) {
                if ($category->getId() === $this->ids->get('Shirts')) {
                    $category->setActive(false);
                }
            }
        };
        $dispatcher->addListener('sales_channel.category.loaded', $listener);

        try {
            static::assertSame([$this->ids->get('deepest') => ['Clothing']], $this->load('deepest'));
        } finally {
            $dispatcher->removeListener('sales_channel.category.loaded', $listener);
        }
    }

    public function testLoadsNothingWithoutProducts(): void
    {
        static::assertSame([], $this->getLoader()->load([], $this->context));
    }

    /**
     * Loads the products like a product box gets them, with their inherited category ids.
     *
     * @return array<string, list<string>>
     */
    private function load(string ...$keys): array
    {
        $ids = array_map(fn (string $key) => $this->ids->get($key), $keys);

        $context = $this->context->getContext();
        $products = $context->enableInheritance(
            static fn () => static::getContainer()->get('product.repository')->search(new Criteria($ids), $context)->getEntities()
        );

        $list = [];
        foreach ($products as $product) {
            static::assertInstanceOf(ProductEntity::class, $product);
            $list[] = $product;
        }

        return $this->getLoader()->load($list, $this->context);
    }

    private function getLoader(): AnalyticsCategoryPathLoader
    {
        return static::getContainer()->get(AnalyticsCategoryPathLoader::class);
    }
}
