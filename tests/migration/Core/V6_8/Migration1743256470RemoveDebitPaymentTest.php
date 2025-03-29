<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_8;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_8\Migration1743256470RemoveDebitPayment;

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
        $this->createPaymentMethodIfNotExists($connection);

        $migration = new Migration1743256470RemoveDebitPayment();
        $migration->update($connection);
        $migration->update($connection);

        static::assertCount(
            0,
            $connection->fetchAllAssociative(
                'SELECT * FROM `payment_method` WHERE `handler_identifier` = :handlerIdentifier AND `active` != 0',
                ['handlerIdentifier' => Migration1743256470RemoveDebitPayment::METHOD_HANDLER],
            )
        );
    }

    private function createPaymentMethodIfNotExists(Connection $connection): void
    {
        $paymentMethod = $connection->fetchOne(
            'SELECT `id` FROM `payment_method` WHERE `handler_identifier` = :handlerIdentifier',
            ['handlerIdentifier' => Migration1743256470RemoveDebitPayment::METHOD_HANDLER],
        );
        if ($paymentMethod) {
            return;
        }

        $connection->executeStatement(
            'INSERT INTO `payment_method` SET
                `id` = :id,
                `handler_identifier` = :handlerIdentifier,
                `technical_name` = :technicalName,
                `created_at` = :createdAt',
            [
                'id' => Uuid::randomBytes(),
                'handlerIdentifier' => Migration1743256470RemoveDebitPayment::METHOD_HANDLER,
                'technicalName' => Uuid::randomHex(),
                'createdAt' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]
        )
        ;
    }
}
