<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class AnalyticsControllerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testReturnsTheCategoryPathOfAProductThroughTheStoreApiBreadcrumb(): void
    {
        $ids = new IdsCollection();
        $salesChannel = $this->getStorefront();
        $this->activateAnalytics($salesChannel);

        static::getContainer()->get('category.repository')->create([[
            'id' => $ids->create('parent'),
            'parentId' => $salesChannel->getNavigationCategoryId(),
            'name' => 'Analytics parent',
            'children' => [
                // ProductBuilder::category() names the category after its key
                ['id' => $ids->create('Analytics child'), 'name' => 'Analytics child'],
            ],
        ]], Context::createDefaultContext());

        $product = (new ProductBuilder($ids, 'analytics-product'))
            ->price(10)
            ->visibility($salesChannel->getId())
            ->category('Analytics child')
            ->build();

        static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());

        $response = $this->request('?productId=' . $ids->get('analytics-product'));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(['Analytics parent', 'Analytics child'], json_decode((string) $response->getContent(), true));
    }

    private function request(string $query): Response
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $_SERVER['APP_URL'] . '/widgets/analytics/product-categories' . $query);

        return $browser->getResponse();
    }

    private function activateAnalytics(SalesChannelEntity $salesChannel): void
    {
        static::getContainer()->get('sales_channel.repository')->update([[
            'id' => $salesChannel->getId(),
            'analytics' => ['trackingId' => 'G-TEST', 'active' => true],
        ]], Context::createDefaultContext());
    }

    private function getStorefront(): SalesChannelEntity
    {
        $salesChannel = static::getContainer()->get('sales_channel.repository')->search(
            (new Criteria())->addFilter(
                new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                new EqualsFilter('domains.url', $_SERVER['APP_URL'])
            ),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        return $salesChannel;
    }
}
