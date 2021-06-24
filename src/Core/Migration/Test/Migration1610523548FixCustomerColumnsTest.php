<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\Migration1610523548FixCustomerColumns;

/**
 * @deprecated tag:v6.5.0
 * this test is no longer necessary, when the old columns are dropped
 */
class Migration1610523548FixCustomerColumnsTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->repository = $this->getContainer()->get('customer.repository');

        $this->connection->rollBack();
        $this->rollback();
        $this->migrate();
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeUpdate('
            ALTER TABLE `customer`
            DROP COLUMN doubleOptInRegistration,
            DROP COLUMN doubleOptInEmailSentDate,
            DROP COLUMN doubleOptInConfirmDate;
        ');
        $this->connection->executeUpdate('DROP TRIGGER IF EXISTS customer_double_opt_in_insert;');
        $this->connection->executeUpdate('DROP TRIGGER IF EXISTS customer_double_opt_in_update;');
        $this->connection->beginTransaction();
        parent::tearDown();
    }

    public function getMigrationClass(): string
    {
        return \Shopware\Core\Migration\V6_4\Migration1610523548FixCustomerColumns::class;
    }

    public function testColumns(): void
    {
        $schemaManager = $this->connection->getSchemaManager();
        $columns = $schemaManager->listTableColumns(CustomerDefinition::ENTITY_NAME);

        static::assertArrayHasKey('double_opt_in_registration', $columns);
        static::assertArrayHasKey('double_opt_in_email_sent_date', $columns);
        static::assertArrayHasKey('double_opt_in_confirm_date', $columns);
    }

    public function testInsertTriggers(): void
    {
        $this->insertTestCustomer();

        $sql = '
            SELECT
                   doubleOptInRegistration,
                   doubleOptInEmailSentDate,
                   doubleOptInConfirmDate,
                   double_opt_in_registration,
                   double_opt_in_email_sent_date,
                   double_opt_in_confirm_date
            FROM `customer`;
        ';

        $doubleOptIn = $this->connection->fetchAssoc($sql);

        static::assertEquals($doubleOptIn['double_opt_in_registration'], $doubleOptIn['doubleOptInRegistration']);
        static::assertEquals($doubleOptIn['double_opt_in_email_sent_date'], $doubleOptIn['doubleOptInEmailSentDate']);
        static::assertEquals($doubleOptIn['double_opt_in_confirm_date'], $doubleOptIn['doubleOptInConfirmDate']);
    }

    public function testUpdateTriggers(): void
    {
        $this->insertTestCustomer();

        /** @var CustomerEntity $customer */
        $customer = $this->repository->search((new Criteria()), Context::createDefaultContext())->first();

        $this->repository->update(
            [
                [
                    'id' => $customer->getId(),
                    'doubleOptInRegistration' => !$customer->getDoubleOptInRegistration(),
                    'doubleOptInEmailSentDate' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'doubleOptInConfirmDate' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            ],
            Context::createDefaultContext()
        );

        $sql = '
            SELECT
                   doubleOptInRegistration,
                   doubleOptInEmailSentDate,
                   doubleOptInConfirmDate,
                   double_opt_in_registration,
                   double_opt_in_email_sent_date,
                   double_opt_in_confirm_date
            FROM `customer`;
        ';

        $doubleOptIn = $this->connection->fetchAssoc($sql);

        static::assertEquals($doubleOptIn['double_opt_in_registration'], $doubleOptIn['doubleOptInRegistration']);
        static::assertEquals($doubleOptIn['double_opt_in_email_sent_date'], $doubleOptIn['doubleOptInEmailSentDate']);
        static::assertEquals($doubleOptIn['double_opt_in_confirm_date'], $doubleOptIn['doubleOptInConfirmDate']);
    }

    private function insertTestCustomer(): void
    {
        $id = Uuid::randomHex();
        $shippingAddressId = Uuid::randomHex();
        $billingAddressId = Uuid::randomHex();
        $salutationId = $this->getSalutationId();

        $customer = [
            'id' => $id,
            'customerNumber' => '1337',
            'salutationId' => $salutationId,
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'email' => 'test@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => $this->getDefaultPaymentMethodId(),
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $billingAddressId,
            'defaultShippingAddressId' => $shippingAddressId,
            'doubleOptInRegistration' => true,
            'doubleOptInEmailSentDate' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'doubleOptInConfirmDate' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $this->repository->create([$customer], Context::createDefaultContext());
    }

    private function getSalutationId(): ?string
    {
        $salutationIds = $this->connection->executeQuery('SELECT id FROM salutation')->fetchAll(FetchMode::COLUMN);

        return Uuid::fromBytesToHex($salutationIds[array_rand($salutationIds)]);
    }

    private function getDefaultPaymentMethodId(): ?string
    {
        $id = $this->connection->executeQuery(
            'SELECT `id` FROM `payment_method` WHERE `active` = 1 ORDER BY `position` ASC'
        )->fetchColumn();

        if (!$id) {
            return null;
        }

        return Uuid::fromBytesToHex($id);
    }

    private function migrate(): void
    {
        (new Migration1610523548FixCustomerColumns())->update($this->connection);
    }

    private function rollback(): void
    {
        $this->connection->executeUpdate('DELETE FROM `customer`;');

        $this->connection->executeUpdate('
            ALTER TABLE `customer`
            DROP COLUMN double_opt_in_registration,
            DROP COLUMN double_opt_in_email_sent_date,
            DROP COLUMN double_opt_in_confirm_date;
        ');

        $this->connection->executeUpdate('DROP TRIGGER IF EXISTS customer_double_opt_in_insert;');
        $this->connection->executeUpdate('DROP TRIGGER IF EXISTS customer_double_opt_in_update;');

        $this->connection->executeUpdate('
            ALTER TABLE `customer`
            ADD COLUMN `doubleOptInRegistration` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`,
            ADD COLUMN `doubleOptInEmailSentDate` DATETIME(3) NULL AFTER `doubleOptInRegistration`,
            ADD COLUMN `doubleOptInConfirmDate` DATETIME(3) NULL AFTER `doubleOptInEmailSentDate`
        ');
    }
}
