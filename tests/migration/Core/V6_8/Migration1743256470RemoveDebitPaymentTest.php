<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1743256470RemoveDebitPayment;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[CoversClass(Migration1743256470RemoveDebitPayment::class)]
class Migration1743256470RemoveDebitPaymentTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testUpdate(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $id = $this->createPaymentMethodIfNotExists($connection);

        $migration = new Migration1743256470RemoveDebitPayment();
        $migration->update($connection);
        $migration->update($connection);

        static::assertCount(
            0,
            $connection->fetchAllAssociative(
                'SELECT * FROM `payment_method` WHERE `id` = :id',
                ['id' => $id],
            )
        );
    }

    public function testUpdateWithBlockingFK(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $id = $this->createPaymentMethodIfNotExists($connection);
        $this->addPaymentMethodToSalesChannel($id, $connection);

        $migration = new Migration1743256470RemoveDebitPayment();
        $migration->update($connection);
        $migration->update($connection);

        static::assertEquals(
            [['active' => 0, 'handler_identifier' => DefaultPayment::class]],
            $connection->fetchAllAssociative(
                'SELECT `active`, `handler_identifier` FROM `payment_method` WHERE `id` = :id',
                ['id' => $id],
            )
        );
    }

    private function createPaymentMethodIfNotExists(Connection $connection): string
    {
        $paymentMethod = $connection->fetchOne(
            'SELECT `id` FROM `payment_method` WHERE `handler_identifier` = :handlerIdentifier',
            ['handlerIdentifier' => Migration1743256470RemoveDebitPayment::METHOD_HANDLER],
        );

        if ($paymentMethod) {
            return $paymentMethod;
        }

        $id = Uuid::randomBytes();

        $connection->insert('payment_method', [
            'id' => $id,
            'handler_identifier' => Migration1743256470RemoveDebitPayment::METHOD_HANDLER,
            'technical_name' => Uuid::randomHex(),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        return $id;
    }

    private function addPaymentMethodToSalesChannel(string $id, Connection $connection): void
    {
        $connection->update(
            'sales_channel',
            ['payment_method_id' => $id],
            ['id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL)],
        );
    }
}
