<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Product\SalesChannel\Review;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelApiTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ProductReviewLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SalesChannelApiTestBehaviour;

    private TestDataCollection $ids;

    private ProductReviewLoader $productReviewLoader;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();
        $this->ids->set('customer-1', $this->createCustomer());
        $this->ids->set('customer-2', $this->createCustomer());

        $this->createProduct();
        $this->createReviews();

        $this->productReviewLoader = $this->getContainer()->get('Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewLoader');

        parent::setUp();
    }

    public function testLoadReviews(): void
    {
        $request = new Request();
        $request->query->set('productId', $this->ids->get('product'));

        $salesChannelContext = $this->createSalesChannelContext();

        $reviewResponse = $this->productReviewLoader->load($request, $salesChannelContext);

        static::assertCount(2, $reviewResponse->getEntities());
        static::assertEquals(3., $reviewResponse->getMatrix()->getAverageRating());
        static::assertNull($reviewResponse->getCustomerReview());
    }

    public function testLoadReviewsIncludingCustomerReview(): void
    {
        $request = new Request();
        $request->query->set('productId', $this->ids->get('product'));

        $salesChannelContext = $this->createSalesChannelContext();
        $customer = new CustomerEntity();
        $customer->setId($this->ids->get('customer-1'));
        $salesChannelContext->assign(['customer' => $customer]);

        $reviewResponse = $this->productReviewLoader->load($request, $salesChannelContext);

        static::assertCount(2, $reviewResponse->getEntities());
        static::assertEquals(3., $reviewResponse->getMatrix()->getAverageRating());

        $customerReview = $reviewResponse->getCustomerReview();
        static::assertNotNull($customerReview);
        static::assertEquals($this->ids->get('review-1'), $customerReview->getId());
    }

    private function createProduct(): void
    {
        $this->getContainer()->get('product.repository')->create([[
            'id' => $this->ids->create('product'),
            'productNumber' => Uuid::randomHex(),
            'stock' => 5,
            'name' => 'Test',
            'isCloseout' => true,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
            'tax' => ['id' => Uuid::randomHex(), 'name' => 'test', 'taxRate' => 19],
            'manufacturer' => ['name' => 'test'],
            'visibilities' => [
                [
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ]], Context::createDefaultContext());
    }

    private function createReviews(): void
    {
        $this->getContainer()->get('product_review.repository')->create([
            [
                'id' => $this->ids->create('review-1'),
                'productId' => $this->ids->get('product'),
                'customerId' => $this->ids->get('customer-1'),
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'status' => true,
                'points' => 2,
                'title' => 'foo1',
                'content' => 'bar1',
            ],
            [
                'id' => $this->ids->create('review-2'),
                'productId' => $this->ids->get('product'),
                'customerId' => $this->ids->get('customer-2'),
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'status' => true,
                'points' => 4,
                'title' => 'foo2',
                'content' => 'bar2',
            ],
        ], Context::createDefaultContext());
    }
}
