<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

/**
 * @internal
 */
#[Package('inventory')]
class ProductPageSeoUrlRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    public function testMainCategories(): void
    {
        $ids = new IdsCollection();

        // Storefront-type sales channels: SEO URLs are generated directly (headless channels only generate for
        // domains marked as external storefront, which is covered in the headless SEO tests).
        $salesChannel1 = $this->createSalesChannel(['domains' => [['url' => 'http://sw-seo-main-category-1.test']]]);
        $salesChannel2 = $this->createSalesChannel(['domains' => [['url' => 'http://sw-seo-main-category-2.test']]]);

        $product = (new ProductBuilder($ids, 'p1'))
            ->price(100)
            ->visibility($salesChannel1['id'])
            ->visibility($salesChannel2['id'])
            ->categories(['c1', 'c2'])
            ->mainCategory($salesChannel1['id'], 'c1')
            ->mainCategory($salesChannel2['id'], 'c2')
            ->build();

        static::getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());

        $this->generateAndAssert(
            ids: array_values($ids->getList(['p1'])),
            template: '{{ product.mainCategories.first.category.translated.name }}',
            salesChannelId: $salesChannel1['id'],
            expected: ['c1']
        );

        $this->generateAndAssert(
            ids: array_values($ids->getList(['p1'])),
            template: '{{ product.mainCategories.first.category.translated.name }}',
            salesChannelId: $salesChannel2['id'],
            expected: ['c2']
        );
    }

    /**
     * @param list<string> $ids
     * @param list<string> $expected
     */
    private function generateAndAssert(array $ids, string $template, string $salesChannelId, array $expected): void
    {
        $context = Context::createDefaultContext();

        $channels = static::getContainer()
            ->get('sales_channel.repository')
            ->search(new Criteria([$salesChannelId]), $context)
            ->getEntities();

        $channel = $channels->get($salesChannelId);

        static::assertInstanceOf(SalesChannelEntity::class, $channel);

        $generator = static::getContainer()->get(SeoUrlGenerator::class);

        $urls = $generator->generate(
            ids: $ids,
            template: $template,
            route: static::getContainer()->get(ProductPageSeoUrlRoute::class),
            context: $context,
            salesChannel: $channel
        );

        $urls = iterator_to_array($urls);
        static::assertCount(\count($expected), $urls);

        foreach ($urls as $url) {
            static::assertContains($url->getSeoPathInfo(), $expected);
        }
    }
}
