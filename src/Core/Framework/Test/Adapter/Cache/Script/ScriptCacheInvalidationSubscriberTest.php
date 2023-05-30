<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Cache\Script;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class ScriptCacheInvalidationSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AppSystemTestBehaviour;

    /**
     * @dataProvider invalidationCasesProvider
     *
     * @param callable(ContainerInterface): void $closure
     */
    public function testCacheInvalidation(string $tagName, callable $closure, bool $shouldBeInvalidated, IdsCollection $ids): void
    {
        $this->createProducts($ids);

        $this->loadAppsFromDir(__DIR__ . '/_fixtures/');

        $cache = $this->getContainer()->get('cache.object');
        $item = $cache->getItem($tagName);
        $item->set('test');
        $item->tag($tagName);
        $cache->save($item);

        $closure($this->getContainer());

        static::assertEquals(!$shouldBeInvalidated, $cache->hasItem($tagName));
    }

    public static function invalidationCasesProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'simple event' => [
            'my-tag',
            function (ContainerInterface $container) use ($ids): void {
                $manufacturerRepo = $container->get('product_manufacturer.repository');

                $manufacturerRepo->upsert([[
                    'id' => $ids->get('m1'),
                    'name' => 'update',
                ]], Context::createDefaultContext());
            },
            true,
            $ids,
        ];

        yield 'filter by entity name invalidates for correct entity' => [
            'my-manufacturer-' . $ids->get('m1'),
            function (ContainerInterface $container) use ($ids): void {
                $manufacturerRepo = $container->get('product_manufacturer.repository');

                $manufacturerRepo->upsert([[
                    'id' => $ids->get('m1'),
                    'name' => 'update',
                ]], Context::createDefaultContext());
            },
            true,
            $ids,
        ];

        yield 'filter by entity name does not invalidate for wrong entity' => [
            'my-manufacturer-' . $ids->get('m1'),
            function (ContainerInterface $container) use ($ids): void {
                $productRepo = $container->get('product.repository');

                $productRepo->upsert([[
                    'id' => $ids->get('p1'),
                    'name' => 'update',
                ]], Context::createDefaultContext());
            },
            false,
            $ids,
        ];

        yield 'complex filter correctly invalidates' => [
            'my-product-' . $ids->get('v4.1'),
            function (ContainerInterface $container) use ($ids): void {
                $productRepo = $container->get('product.repository');

                $product4 = (new ProductBuilder($ids, 'p4'))
                    ->price(100)
                    ->variant(
                        (new ProductBuilder($ids, 'v4.1'))
                            ->build()
                    );

                $productRepo->create([$product4->build()], Context::createDefaultContext());
            },
            true,
            $ids,
        ];

        yield 'complex filter does not invalidate wrong operation' => [
            'my-product-' . $ids->get('p2'),
            function (ContainerInterface $container) use ($ids): void {
                $productRepo = $container->get('product.repository');

                $productRepo->delete([['id' => $ids->get('p2')]], Context::createDefaultContext());
            },
            false,
            $ids,
        ];

        yield 'complex filter does not invalidate with missing payload' => [
            'my-product-' . $ids->get('p4'),
            function (ContainerInterface $container) use ($ids): void {
                $productRepo = $container->get('product.repository');

                $product4 = (new ProductBuilder($ids, 'p4'))
                    ->price(100);

                $productRepo->create([$product4->build()], Context::createDefaultContext());
            },
            false,
            $ids,
        ];
    }

    private function createProducts(IdsCollection $ids): void
    {
        $product1 = (new ProductBuilder($ids, 'p1'))
            ->price(100)
            ->manufacturer('m1')
            ->variant(
                (new ProductBuilder($ids, 'v1.1'))
                    ->build()
            );

        $product2 = (new ProductBuilder($ids, 'p2'))
            ->price(200)
            ->active(false);

        $product3 = (new ProductBuilder($ids, 'p3'))
            ->price(300);

        $this->getContainer()->get('product.repository')->create([
            $product1->build(),
            $product2->build(),
            $product3->build(),
        ], Context::createDefaultContext());
    }
}
