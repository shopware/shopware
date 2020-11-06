<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\FieldSerializer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

class StateMachineSateFieldSerializerTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    /**
     * @var EntityRepository
     */
    private $orderRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Connection
     */
    private $connection;

    public function setUp(): void
    {
        parent::setUp();

        $this->orderRepository = $this->getContainer()->get('order.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testEncodeEveryStateMachineStateIdAllowedWhenCreatingEntity(): void
    {
        $payload = array_merge(
            $this->createOrderPayload(),
            [
                'stateId' => $this->fetchOrderStateId(OrderStates::STATE_COMPLETED),
            ]
        );

        $this->context->scope(Context::USER_SCOPE, function (Context $context) use ($payload): void {
            $this->orderRepository->create([$payload], $context);
        });

        // Expect no exception was thrown
        $this->addToAssertionCount(1);
    }

    public function testEncodeChaingingStateMachineStateIdNotAllowedWhenWrongScope(): void
    {
        $payload = $this->createOrderPayload();
        $this->orderRepository->create([$payload], $this->context);

        $this->expectException(WriteException::class);
        $this->expectExceptionMessageMatches('|There are 1 error\\(s\\) while writing data\\.|');
        $this->expectExceptionMessageMatches(
            '|\\[/0/stateId\\] Changing the state-machine-state of this entity is not allowed for scope user\\. '
            . 'Either change the state-machine-state via a state-transition or use a different scope\\.|'
        );

        $this->context->scope(Context::USER_SCOPE, function (Context $context) use ($payload): void {
            $this->orderRepository->update([
                [
                    'id' => $payload['id'],
                    'stateId' => $this->fetchOrderStateId(OrderStates::STATE_COMPLETED),
                ],
            ], $context);
        });
    }

    public function testEncodeChaingingStateMachineStateIdAllowedWhenCorrectScope(): void
    {
        $payload = $this->createOrderPayload();
        $this->orderRepository->create([$payload], $this->context);

        $this->context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($payload): void {
            $this->orderRepository->update([
                [
                    'id' => $payload['id'],
                    'stateId' => $this->fetchOrderStateId(OrderStates::STATE_COMPLETED),
                ],
            ], $context);
        });

        // Expect no exception was thrown
        $this->addToAssertionCount(1);
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
            'salesChannelId' => Defaults::SALES_CHANNEL,
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
        return Uuid::fromBytesToHex((string) $this->connection->fetchColumn("SELECT id FROM {$table} LIMIT 1"));
    }

    private function fetchOrderStateId(string $orderStateTechnicalName): string
    {
        $id = $this->connection->fetchColumn(
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
