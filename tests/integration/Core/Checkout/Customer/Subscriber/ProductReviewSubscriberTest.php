<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
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
        $this->productReviewRepository->create([[
            'productId' => $this->ids->get('product'),
            'customerId' => $this->ids->get('customer'),
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'title' => 'fooo',
            'content' => 'baar',
        ],
            [
                'productId' => $this->ids->get('product'),
                'customerId' => $this->ids->get('customer'),
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'title' => 'fooo',
                'content' => 'baar',
            ]], Context::createDefaultContext());

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->first();

        static::assertSame(2, $customer->getReviewCount());
    }

    public function testDeletingNewReview(): void
    {
        $this->productReviewRepository->create([[
            'id' => $this->ids->create('review'),
            'productId' => $this->ids->get('product'),
            'customerId' => $this->ids->get('customer'),
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'title' => 'fooo',
            'content' => 'baar',
        ],
            [
                'id' => $this->ids->create('review-2'),
                'productId' => $this->ids->get('product'),
                'customerId' => $this->ids->get('customer'),
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'title' => 'fooo',
                'content' => 'baar',
            ]], Context::createDefaultContext());

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->first();

        static::assertSame(2, $customer->getReviewCount());

        $this->productReviewRepository->delete([['id' => $this->ids->get('review')], ['id' => $this->ids->get('review-2')]], Context::createDefaultContext());

        /** @var CustomerEntity $customer */
        $customer = $this->customerRepository->search(
            new Criteria([$this->ids->get('customer')]),
            Context::createDefaultContext()
        )->first();

        static::assertSame(0, $customer->getReviewCount());
    }

    private function createProduct(): void
    {
        $product = [
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
        ];

        $this->productRepository->create([$product], Context::createDefaultContext());
    }

    private function createCustomer(?string $password = null, ?string $email = null, ?bool $guest = false): void
    {
        $customerId = $this->ids->create('customer');
        $addressId = Uuid::randomHex();

        if ($email === null) {
            $email = Uuid::randomHex() . '@example.com';
        }

        if ($password === null) {
            $password = Uuid::randomHex();
        }

        $this->customerRepository->create([
            [
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'active' => true,
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'availabilityRule' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'cartCartAmount',
                                'value' => [
                                    'operator' => '>=',
                                    'amount' => 0,
                                ],
                            ],
                        ],
                    ],
                    'salesChannels' => [
                        [
                            'id' => TestDefaults::SALES_CHANNEL,
                        ],
                    ],
                ],
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'guest' => $guest,
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], Context::createDefaultContext());
    }
}
