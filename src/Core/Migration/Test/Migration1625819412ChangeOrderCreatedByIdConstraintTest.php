<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_4\Migration1625819412ChangeOrderCreatedByIdConstraint;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('core')]
class Migration1625819412ChangeOrderCreatedByIdConstraintTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->connection->rollBack();
        $this->connection->executeStatement('
            ALTER TABLE `order`
            DROP FOREIGN KEY `fk.order.created_by_id`,
            DROP FOREIGN KEY `fk.order.updated_by_id`;
        ');

        $this->connection->executeStatement('
            ALTER TABLE `order`
            ADD CONSTRAINT `fk.order.created_by_id` FOREIGN KEY (`created_by_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
            ADD CONSTRAINT `fk.order.updated_by_id` FOREIGN KEY (`updated_by_id`) REFERENCES `user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
        ');

        $migration = new Migration1625819412ChangeOrderCreatedByIdConstraint();
        $migration->update($this->connection);

        $this->connection->beginTransaction();
    }

    public function testItChangesOrderCreatedByIdConstraint(): void
    {
        $context = Context::createDefaultContext();
        $userId = Uuid::randomHex();

        $userRepository = $this->createUserWithId($userId);

        $orderPayload = $this->createOrderPayload();
        $orderPayload['createdById'] = $userId;
        $orderRepository = $this->getContainer()->get('order.repository');
        $orderRepository->create([$orderPayload], $context);

        $userRepository->delete([['id' => $userId]], $context);

        /** @var OrderEntity $order */
        $order = $orderRepository->search(new Criteria([$orderPayload['id']]), $context)->first();
        static::assertNull($order->getCreatedById());
    }

    public function testItChangesOrderUpdatedByIdConstraint(): void
    {
        $context = Context::createDefaultContext();
        $userId = Uuid::randomHex();

        $userRepository = $this->createUserWithId($userId);

        $orderPayload = $this->createOrderPayload();
        $orderPayload['updatedById'] = $userId;
        $orderRepository = $this->getContainer()->get('order.repository');
        $orderRepository->create([$orderPayload], $context);

        $userRepository->delete([['id' => $userId]], $context);

        /** @var OrderEntity $order */
        $order = $orderRepository->search(new Criteria([$orderPayload['id']]), $context)->first();
        static::assertNull($order->getUpdatedById());
    }

    private function createUserWithId(string $userId): EntityRepository
    {
        $userRepository = $this->getContainer()->get('user.repository');
        $userRepository->create([
            [
                'id' => $userId,
                'firstName' => $userId,
                'lastName' => 'Test',
                'username' => $userId,
                'email' => 'Test@test.com',
                'password' => password_hash($userId, \PASSWORD_BCRYPT),
                'localeId' => $this->getLocaleIdOfSystemLanguage(),
                'active' => true,
            ],
        ], Context::createDefaultContext());

        return $userRepository;
    }

    /**
     * @return array<string, mixed>
     */
    private function createOrderPayload(): array
    {
        $addressId = Uuid::randomHex();

        return [
            'id' => Uuid::randomHex(),
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'id' => Uuid::randomHex(),
                'email' => 'test@example.com',
                'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $this->fetchOrderStateId(OrderStates::STATE_OPEN),
            'paymentMethodId' => $this->fetchFirstIdFromTable('payment_method'),
            'currencyId' => Defaults::CURRENCY,
            'currencyFactor' => 1.0,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'billingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                    'countryId' => $this->fetchFirstIdFromTable('country'),
                ],
            ],
            'lineItems' => [],
            'deliveries' => [],
            'context' => '{}',
            'payload' => '{}',
        ];
    }

    private function fetchFirstIdFromTable(string $table): string
    {
        return Uuid::fromBytesToHex((string) $this->connection->fetchOne("SELECT id FROM {$table} LIMIT 1"));
    }

    private function fetchOrderStateId(string $orderStateTechnicalName): string
    {
        $id = $this->connection->fetchOne(
            'SELECT state_machine_state.id
            FROM state_machine_state
            JOIN state_machine ON state_machine_state.state_machine_id = state_machine.id
            WHERE
                state_machine.technical_name = :orderStateMachineTechnicalName
                AND state_machine_state.technical_name = :orderStateTechnicalName',
            [
                'orderStateMachineTechnicalName' => OrderStates::STATE_MACHINE,
                'orderStateTechnicalName' => $orderStateTechnicalName,
            ]
        );

        return Uuid::fromBytesToHex((string) $id);
    }
}
