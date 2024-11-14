<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Service\ProductReviewCountService;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\Builder\Customer\CustomerBuilder;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(ProductReviewCountService::class)]
class ProductReviewCountServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private ProductReviewCountService $reviewCountService;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->reviewCountService = $this->getContainer()->get(ProductReviewCountService::class);
    }

    public function testReviewCountIsUpdatedCorrectly(): void
    {
        $this->createProduct('p1');
        $this->createProduct('p2');

        $this->createCustomer('c1');
        $createdReviews[] = $this->createReview('c1', 'p1', true);
        $createdReviews[] = $this->createReview('c1', 'p2', false);

        $this->createCustomer('c2');
        $createdReviews[] = $this->createReview('c2', 'p2', true);

        $this->reviewCountService->updateReviewCount($createdReviews);

        $customerRepo = $this->getContainer()->get('customer.repository');
        /** @var CustomerCollection $customers */
        $customers = $customerRepo->search(new Criteria([$this->ids->get('c1'), $this->ids->get('c2')]), Context::createDefaultContext());

        $firstCustomer = $customers->get($this->ids->get('c1'));
        static::assertInstanceOf(CustomerEntity::class, $firstCustomer);
        static::assertEquals(1, $firstCustomer->getReviewCount());

        $secondCustomer = $customers->get($this->ids->get('c2'));
        static::assertInstanceOf(CustomerEntity::class, $secondCustomer);
        static::assertEquals(1, $secondCustomer->getReviewCount());
    }

    private function createCustomer(string $customerNumber): void
    {
        $customer = (new CustomerBuilder(
            $this->ids,
            $customerNumber
        ))->build();

        $customerRepo = $this->getContainer()->get('customer.repository');
        $customerRepo->create([$customer], Context::createDefaultContext());
    }

    private function createProduct(string $productNumber): void
    {
        $product = new ProductBuilder(
            $this->ids,
            $productNumber
        );
        $product->price(100);

        $productRepo = $this->getContainer()->get('product.repository');
        $productRepo->create([$product->build()], Context::createDefaultContext());
    }

    private function createReview(string $customerNumber, string $productNumber, bool $status): string
    {
        $productReviewRepo = $this->getContainer()->get('product_review.repository');

        $id = Uuid::randomHex();

        $productReviewRepo->create([
            [
                'id' => $id,
                'customerId' => $this->ids->get($customerNumber),
                'productId' => $this->ids->get($productNumber),
                'status' => $status,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'title' => 'foo',
                'content' => 'bar',
                'points' => 3,
            ],
        ], Context::createDefaultContext());

        return $id;
    }
}
