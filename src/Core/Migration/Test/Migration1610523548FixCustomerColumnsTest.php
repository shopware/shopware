<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1610523548FixCustomerColumns;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('core')]
class Migration1610523548FixCustomerColumnsTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private EntityRepository $repository;

    private EntityWriter $writer;

    private CustomerDefinition $customerDefinition;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->writer = $this->getContainer()->get(EntityWriter::class);
        $this->customerDefinition = $this->getContainer()->get(CustomerDefinition::class);
        $this->repository = $this->getContainer()->get('customer.repository');

        $this->connection->rollBack();
        $this->rollback();
        $this->migrate();
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->connection->rollBack();
        $this->connection->executeStatement('
            ALTER TABLE `customer`
            DROP COLUMN doubleOptInRegistration,
            DROP COLUMN doubleOptInEmailSentDate,
            DROP COLUMN doubleOptInConfirmDate;
        ');
        $this->connection->executeStatement('DROP TRIGGER IF EXISTS customer_double_opt_in_insert;');
        $this->connection->executeStatement('DROP TRIGGER IF EXISTS customer_double_opt_in_update;');
        $this->connection->beginTransaction();
        parent::tearDown();
    }

    public function testColumns(): void
    {
        $schemaManager = $this->connection->createSchemaManager();
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

        $doubleOptIn = $this->connection->fetchAssociative($sql);

        static::assertIsArray($doubleOptIn);
        static::assertEquals($doubleOptIn['double_opt_in_registration'], $doubleOptIn['doubleOptInRegistration']);
        static::assertEquals($doubleOptIn['double_opt_in_email_sent_date'], $doubleOptIn['doubleOptInEmailSentDate']);
        static::assertEquals($doubleOptIn['double_opt_in_confirm_date'], $doubleOptIn['doubleOptInConfirmDate']);
    }

    public function testUpdateTriggers(): void
    {
        $this->insertTestCustomer();

        /** @var CustomerEntity $customer */
        $customer = $this->repository->search((new Criteria()), Context::createDefaultContext())->first();

        $this->writer->update(
            $this->customerDefinition,
            [
                [
                    'id' => $customer->getId(),
                    'doubleOptInRegistration' => !$customer->getDoubleOptInRegistration(),
                    'doubleOptInEmailSentDate' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                    'doubleOptInConfirmDate' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            ],
            WriteContext::createFromContext(Context::createDefaultContext())
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

        $doubleOptIn = $this->connection->fetchAssociative($sql);

        static::assertIsArray($doubleOptIn);
        static::assertEquals($doubleOptIn['double_opt_in_registration'], $doubleOptIn['doubleOptInRegistration']);
        static::assertEquals($doubleOptIn['double_opt_in_email_sent_date'], $doubleOptIn['doubleOptInEmailSentDate']);
        static::assertEquals($doubleOptIn['double_opt_in_confirm_date'], $doubleOptIn['doubleOptInConfirmDate']);
    }

    private function insertTestCustomer(): void
    {
        $id = Uuid::randomHex();
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
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => [
                'id' => UUID::randomHex(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schoöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'defaultBillingAddress' => [
                'id' => Uuid::randomHex(),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'street' => 'Musterstraße 1',
                'city' => 'Schoöppingen',
                'zipcode' => '12345',
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId(),
            ],
            'doubleOptInRegistration' => true,
            'doubleOptInEmailSentDate' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'doubleOptInConfirmDate' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $this->writer->insert($this->customerDefinition, [$customer], WriteContext::createFromContext(Context::createDefaultContext()));
    }

    private function getSalutationId(): string
    {
        $salutationIds = $this->connection->fetchFirstColumn('SELECT id FROM salutation');

        return Uuid::fromBytesToHex($salutationIds[array_rand($salutationIds)]);
    }

    private function getDefaultPaymentMethodId(): ?string
    {
        $id = $this->connection->executeQuery(
            'SELECT `id` FROM `payment_method` WHERE `active` = 1 ORDER BY `position` ASC'
        )->fetchOne();

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
        $this->connection->executeStatement('DELETE FROM `customer`;');

        $this->connection->executeStatement('
            ALTER TABLE `customer`
            DROP COLUMN double_opt_in_registration,
            DROP COLUMN double_opt_in_email_sent_date,
            DROP COLUMN double_opt_in_confirm_date;
        ');

        $this->connection->executeStatement('DROP TRIGGER IF EXISTS customer_double_opt_in_insert;');
        $this->connection->executeStatement('DROP TRIGGER IF EXISTS customer_double_opt_in_update;');

        $this->connection->executeStatement('
            ALTER TABLE `customer`
            ADD COLUMN `doubleOptInRegistration` TINYINT(1) NOT NULL DEFAULT 0 AFTER `active`,
            ADD COLUMN `doubleOptInEmailSentDate` DATETIME(3) NULL AFTER `doubleOptInRegistration`,
            ADD COLUMN `doubleOptInConfirmDate` DATETIME(3) NULL AFTER `doubleOptInEmailSentDate`
        ');
    }
}
