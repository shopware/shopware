<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;

class UpdatedByFieldTest extends TestCase
{
    use IntegrationTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;

    public function testUpdatedByNotUpdateWithWrongScope(): void
    {
        /** @var EntityRepositoryInterface $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');
        $userId = $this->fetchFirstIdFromTable('user');
        $context = $this->getAdminContext($userId);

        $payload = $this->createOrderPayload();
        $orderRepository->create([$payload], $context);

        $orderRepository->update([
            [
                'id' => $payload['id'],
                'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ],
        ], $context);

        $result = $orderRepository->search(
            new Criteria([$payload['id']]),
            $context
        )->first();

        static::assertNull($result->getUpdatedById());
    }

    public function testUpdatedByNotUpdateWithWrongSource(): void
    {
        /** @var EntityRepositoryInterface $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');
        $context = Context::createDefaultContext();

        $payload = $this->createOrderPayload();
        $orderRepository->create([$payload], $context);

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($orderRepository, $payload): void {
            $orderRepository->update([
                [
                    'id' => $payload['id'],
                    'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            ], $context);
        });

        $result = $orderRepository->search(
            new Criteria([$payload['id']]),
            $context
        )->first();

        static::assertNull($result->getUpdatedById());
    }

    public function testCreateUpdatedBy(): void
    {
        /** @var EntityRepositoryInterface $orderRepository */
        $orderRepository = $this->getContainer()->get('order.repository');
        $userId = $this->fetchFirstIdFromTable('user');
        $context = $this->getAdminContext($userId);

        $payload = $this->createOrderPayload();
        $orderRepository->create([$payload], $context);

        $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($orderRepository, $payload): void {
            $orderRepository->update([
                [
                    'id' => $payload['id'],
                    'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                ],
            ], $context);
        });

        $result = $orderRepository->search(
            new Criteria([$payload['id']]),
            $context
        )->first();

        static::assertEquals($userId, $result->getUpdatedById());
    }

    private function getAdminContext($userId): Context
    {
        $source = new AdminApiSource($userId);
        $source->setPermissions([
            'order:list',
            'order:create',
            'order:update',
            'order_customer:create',
            'order_address:create',
        ]);

        return new Context($source);
    }

    private function createOrderPayload(): array
    {
        $addressId = Uuid::randomHex();

        return [
            'id' => Uuid::randomHex(),
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
        return Uuid::fromBytesToHex((string) $this->getContainer()->get(Connection::class)->fetchColumn("SELECT id FROM {$table} LIMIT 1"));
    }

    private function fetchOrderStateId(string $orderStateTechnicalName): string
    {
        $id = $this->getContainer()->get(Connection::class)->fetchColumn(
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
