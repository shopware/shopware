<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1616076922AppPaymentMethod;
use Shopware\Core\Migration\V6_4\Migration1643386819AddPreparedPaymentsToAppPaymentMethod;
use Shopware\Core\Migration\V6_4\Migration1647511158AddRefundUrlToAppPaymentMethod;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1616076922AppPaymentMethod
 */
class Migration1616076922AppPaymentMethodTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testNoAppPaymentMethodTable(): void
    {
        $this->connection->executeStatement('DROP TABLE IF EXISTS `app_payment_method`');

        $migration = new Migration1616076922AppPaymentMethod();
        $migration->update($this->connection);
        $exists = $this->connection->fetchFirstColumn('SELECT COUNT(*) FROM `app_payment_method`') !== false;

        static::assertTrue($exists);

        // we need to execute additional migrations to restore the final table state
        $migration = new Migration1643386819AddPreparedPaymentsToAppPaymentMethod();
        $migration->update($this->connection);

        $migration = new Migration1647511158AddRefundUrlToAppPaymentMethod();
        $migration->update($this->connection);
    }

    public function testDefaultFolder(): void
    {
        $this->connection->delete(
            'media_default_folder',
            [
                'entity' => PaymentMethodDefinition::ENTITY_NAME,
            ]
        );

        $migration = new Migration1616076922AppPaymentMethod();
        $migration->update($this->connection);

        $associationFields = $this->connection->fetchOne('SELECT `association_fields` FROM `media_default_folder` WHERE `entity` = ?', [PaymentMethodDefinition::ENTITY_NAME]);

        static::assertSame(['paymentMethods'], json_decode((string) $associationFields, true, \JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR));
    }
}
