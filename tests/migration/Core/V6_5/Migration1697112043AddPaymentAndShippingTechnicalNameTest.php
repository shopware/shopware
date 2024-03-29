<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\CashPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DebitPayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\InvoicePayment;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PrePayment;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_5\Migration1697112043AddPaymentAndShippingTechnicalName;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1697112043AddPaymentAndShippingTechnicalName::class)]
class Migration1697112043AddPaymentAndShippingTechnicalNameTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1697112043, (new Migration1697112043AddPaymentAndShippingTechnicalName())->getCreationTimestamp());
    }

    public function testMigrate(): void
    {
        $this->rollback();
        $this->migrate();
        $this->migrate();

        $manager = $this->connection->createSchemaManager();
        $columns = $manager->listTableColumns(PaymentMethodDefinition::ENTITY_NAME);

        static::assertArrayHasKey('technical_name', $columns);
        static::assertFalse($columns['technical_name']->getNotnull());

        $columns = $manager->listTableColumns(ShippingMethodDefinition::ENTITY_NAME);

        static::assertArrayHasKey('technical_name', $columns);
        static::assertFalse($columns['technical_name']->getNotnull());

        $names = $this->connection->fetchFirstColumn(
            'SELECT `technical_name` FROM `payment_method` WHERE `handler_identifier` IN (:handlers)',
            ['handlers' => [DebitPayment::class, InvoicePayment::class, CashPayment::class, PrePayment::class]],
            ['handlers' => ArrayParameterType::STRING]
        );

        static::assertCount(4, $names);
        static::assertContains('payment_debitpayment', $names);
        static::assertContains('payment_invoicepayment', $names);
        static::assertContains('payment_cashpayment', $names);
        static::assertContains('payment_prepayment', $names);

        $names = $this->connection->fetchFirstColumn(
            '
            SELECT `technical_name`
            FROM `shipping_method`
            JOIN `shipping_method_translation` ON `shipping_method`.`id` = `shipping_method_translation`.`shipping_method_id`
            WHERE `shipping_method_translation`.`name` IN (:names) AND `shipping_method_translation`.`language_id` = :languageId;
            ',
            ['names' => ['Standard', 'Express'], 'languageId' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)],
            ['names' => ArrayParameterType::STRING]
        );

        static::assertCount(2, $names);
        static::assertContains('shipping_standard', $names);
        static::assertContains('shipping_express', $names);
    }

    private function migrate(): void
    {
        (new Migration1697112043AddPaymentAndShippingTechnicalName())->update($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `payment_method` DROP INDEX `uniq.technical_name`');
        $this->connection->executeStatement('ALTER TABLE `shipping_method` DROP INDEX `uniq.technical_name`');
        $this->connection->executeStatement('ALTER TABLE `payment_method` DROP COLUMN `technical_name`');
        $this->connection->executeStatement('ALTER TABLE `shipping_method` DROP COLUMN `technical_name`');
    }
}
