<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1679584289AddCustomerReviewCount;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(Migration1679584289AddCustomerReviewCount::class)]
class Migration1679584289AddCustomerReviewCountTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->ids = new TestDataCollection();
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement(
                'ALTER TABLE `customer` DROP COLUMN `review_count`;'
            );
        } catch (\Throwable) {
        }
    }

    public function testUpdate(): void
    {
        $migration = new Migration1679584289AddCustomerReviewCount();

        $this->createCustomer();
        $this->createProduct();
        $this->createReview($this->ids->create('review1'));
        $this->createReview($this->ids->create('review2'));
        $this->createReview($this->ids->create('review3'));

        $migration->update($this->connection);
        $migration->update($this->connection);

        $reviewCount = $this->connection->fetchOne(
            'SELECT `review_count` FROM `customer` WHERE `id` = :customerId;',
            ['customerId' => Uuid::fromHexToBytes($this->ids->get('customer'))],
        );
        static::assertEquals(3, $reviewCount);

        // created reviews are deleted over the cascade of the customer
        $this->connection->delete('customer', ['id' => Uuid::fromHexToBytes($this->ids->get('customer'))]);
        $this->connection->delete('product', ['id' => Uuid::fromHexToBytes($this->ids->get('product'))]);
    }

    private function createCustomer(): void
    {
        $billingAddressId = $this->ids->create('billingAddress');
        $defaultCountry = $this->connection->fetchOne('SELECT id FROM country WHERE active = 1 ORDER BY `position`');
        $defaultPaymentMethod = $this->connection->fetchOne('SELECT id FROM payment_method WHERE active = 1 ORDER BY `position`');
        $now = new \DateTimeImmutable();

        $customerAddress = [
            'id' => Uuid::fromHexToBytes($billingAddressId),
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'street' => 'Musterstraße 1',
            'city' => 'Schöppingen',
            'zipcode' => '12345',
            'country_id' => $defaultCountry,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'customer_id' => Uuid::fromHexToBytes($this->ids->get('customer')),
        ];

        $customer = [
            'id' => Uuid::fromHexToBytes($this->ids->create('customer')),
            'customer_number' => '1337',
            'first_name' => 'Max',
            'last_name' => 'Mustermann',
            'email' => 'test@example.com',
            'password' => 'shopware',
            'created_at' => $now->format('Y-m-d H:i:s'),
            'default_payment_method_id' => $defaultPaymentMethod,
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'customer_group_id' => Uuid::fromHexToBytes(TestDefaults::FALLBACK_CUSTOMER_GROUP),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'default_billing_address_id' => Uuid::fromHexToBytes($billingAddressId),
            'default_shipping_address_id' => Uuid::randomBytes(),
        ];

        $this->connection->insert('customer', $customer);
        $this->connection->insert('customer_address', $customerAddress);
    }

    private function createProduct(): void
    {
        /** @var EntityRepository $productRepository */
        $productRepository = self::getContainer()->get('product.repository');

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

        $productRepository->create([$product], Context::createDefaultContext());
    }

    private function createReview(string $reviewId): void
    {
        $review = [
            'id' => Uuid::fromHexToBytes($reviewId),
            'product_id' => Uuid::fromHexToBytes($this->ids->create('product')),
            'product_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'customer_id' => Uuid::fromHexToBytes($this->ids->create('customer')),
            'title' => 'Nice title',
            'content' => 'Nice content',
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ];

        $this->connection->insert(ProductReviewDefinition::ENTITY_NAME, $review);
    }
}
