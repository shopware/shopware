<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\TestDefaults;

/**
 * @package business-ops
 *
 * @internal
 *
 * @covers \Shopware\Core\Checkout\Customer\Subscriber\ProductReviewSubscriber
 */
class ProductReviewSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private TestDataCollection $ids;

    private EntityRepository $productReviewRepository;

    private EntityRepository $customerRepository;

    private EntityRepository $productRepository;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();

        /** @var EntityRepository $productReviewRepository */
        $productReviewRepository = $this->getContainer()->get('product_review.repository');
        $this->productReviewRepository = $productReviewRepository;

        /** @var EntityRepository $customerRepository */
        $customerRepository = $this->getContainer()->get('customer.repository');
        $this->customerRepository = $customerRepository;

        /** @var EntityRepository $productRepository */
        $productRepository = $this->getContainer()->get('product.repository');
        $this->productRepository = $productRepository;

        $this->createCustomer();
        $this->createProduct();
    }

    public function testCreatingNewReview(): void
    {
        $this->createReviews();

        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(1, $customer->getReviewCount());
    }

    public function testDeletingNewReview(): void
    {
        $this->createReviews();

        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(1, $customer->getReviewCount());

        $this->productReviewRepository->delete([['id' => $this->ids->get('review')], ['id' => $this->ids->get('review-2')]], Context::createDefaultContext());

        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(0, $customer->getReviewCount());
    }

    public function testUpdateReviews(): void
    {
        $this->createReviews();

        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(1, $customer->getReviewCount());

        $this->productReviewRepository->update([
            [
                'id' => $this->ids->get('review'),
                'content' => 'foo',
            ],
        ], Context::createDefaultContext());

        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(1, $customer->getReviewCount());

        $this->productReviewRepository->update([
            [
                'id' => $this->ids->get('review-2'),
                'status' => true,
            ],
        ], Context::createDefaultContext());

        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->first();
        static::assertInstanceOf(CustomerEntity::class, $customer);
        static::assertSame(2, $customer->getReviewCount());
    }

    private function createProduct(): void
    {
        $builder = new ProductBuilder($this->ids, 'product');
        $builder->price(10);

        $this->productRepository->create([$builder->build()], Context::createDefaultContext());
    }

    private function createCustomer(): void
    {
        $builder = new CustomerBuilder($this->ids, 'customer');

        $this->customerRepository->create([$builder->build()], Context::createDefaultContext());
    }

    private function createReviews(): void
    {
        $this->productReviewRepository->create([
            [
                'id' => $this->ids->create('review'),
                'productId' => $this->ids->get('product'),
                'customerId' => $this->ids->get('customer'),
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'title' => 'fooo',
                'content' => 'baar',
                'status' => true,
            ],
            [
                'id' => $this->ids->create('review-2'),
                'productId' => $this->ids->get('product'),
                'customerId' => $this->ids->get('customer'),
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'title' => 'fooo',
                'content' => 'baar',
                'status' => false,
            ],
        ], Context::createDefaultContext());
    }
}
