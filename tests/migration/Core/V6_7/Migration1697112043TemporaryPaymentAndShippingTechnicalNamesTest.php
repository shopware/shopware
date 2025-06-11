<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1697112043TemporaryPaymentAndShippingTechnicalNames;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1697112043TemporaryPaymentAndShippingTechnicalNames::class)]
class Migration1697112043TemporaryPaymentAndShippingTechnicalNamesTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Migration1697112043TemporaryPaymentAndShippingTechnicalNames $migration;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
        $this->migration = new Migration1697112043TemporaryPaymentAndShippingTechnicalNames();
        $this->ids = new IdsCollection();
    }

    #[After]
    public function cleanUp(): void
    {
        $byteList = Uuid::fromHexToBytesList(\array_values($this->ids->all()));

        foreach (['payment_method', 'shipping_method', 'app_shipping_method', 'app_payment_method'] as $table) {
            $this->connection->executeStatement(
                'DELETE FROM `' . $table . '` WHERE `id` in (:ids)',
                ['ids' => $byteList],
                ['ids' => ArrayParameterType::BINARY],
            );
        }
    }

    public function testMigrate(): void
    {
        $this->rollback();
        $this->prepare();

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $paymentMethods = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as id, technical_name FROM `payment_method` WHERE `id` IN (:ids)',
            ['ids' => $this->ids->getByteList(['payment-method', 'payment-method--app'])],
            ['ids' => ArrayParameterType::BINARY],
        );

        $shippingMethods = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) as id, technical_name FROM `shipping_method` WHERE `id` IN (:ids)',
            ['ids' => $this->ids->getByteList(['shipping-method', 'shipping-method--app'])],
            ['ids' => ArrayParameterType::BINARY],
        );

        static::assertSame([
            [
                'id' => $this->ids->get('payment-method'),
                'technical_name' => 'temporary_' . $this->ids->get('payment-method'),
            ],
            [
                'id' => $this->ids->get('payment-method--app'),
                'technical_name' => 'payment_testapp_credit_card',
            ],
        ], $paymentMethods);

        static::assertSame([
            [
                'id' => $this->ids->get('shipping-method'),
                'technical_name' => 'temporary_' . $this->ids->get('shipping-method'),
            ],
            [
                'id' => $this->ids->get('shipping-method--app'),
                'technical_name' => 'shipping_TestApp_express_transport',
            ],
        ], $shippingMethods);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `payment_method` MODIFY COLUMN `technical_name` VARCHAR(255) NULL');
        $this->connection->executeStatement('ALTER TABLE `shipping_method` MODIFY COLUMN `technical_name` VARCHAR(255) NULL');
    }

    /**
     * Setup a payment and shipping method with and without an app
     */
    private function prepare(): void
    {
        $this->ids->set(
            'delivery-time',
            $this->connection->fetchOne('SELECT HEX(id) FROM `delivery_time` LIMIT 1'),
        );

        $this->connection->insert('payment_method', [
            'id' => $this->ids->getBytes('payment-method'),
            'handler_identifier' => 'Migration\Payment\Method',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('payment_method', [
            'id' => $this->ids->getBytes('payment-method--app'),
            'handler_identifier' => 'app\TestApp_credit_card',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('shipping_method', [
            'id' => $this->ids->getBytes('shipping-method'),
            'delivery_time_id' => $this->ids->getBytes('delivery-time'),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('shipping_method', [
            'id' => $this->ids->getBytes('shipping-method--app'),
            'delivery_time_id' => $this->ids->getBytes('delivery-time'),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('app_shipping_method', [
            'id' => $this->ids->getBytes('app-shipping-method'),
            'app_name' => 'TestApp',
            'shipping_method_id' => $this->ids->getBytes('shipping-method--app'),
            'identifier' => 'express_transport',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        $this->connection->insert('app_payment_method', [
            'id' => $this->ids->getBytes('app-payment-method'),
            'app_name' => 'TestApp',
            'payment_method_id' => $this->ids->getBytes('payment-method--app'),
            'identifier' => 'credit_card',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
