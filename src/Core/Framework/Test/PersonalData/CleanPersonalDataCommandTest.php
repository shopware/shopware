<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\PersonalData;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\CashRoundingConfig;
use Shopware\Core\Framework\Demodata\PersonalData\CleanPersonalDataCommand;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @internal
 */
class CleanPersonalDataCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private EntityRepository $customerRepository;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->customerRepository = $this->getContainer()->get('customer.repository');
        $this->clearTable('cart');
        $this->clearTable('customer');
    }

    public function testCommandWithoutArguments(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->getCommand()->run($this->getArrayInput(), new BufferedOutput());
    }

    public function testCommandWithInvalidArguments(): void
    {
        $input = new ArrayInput(['type' => 'foo'], $this->createInputDefinition());

        $this->expectException(\InvalidArgumentException::class);
        $this->getCommand()->run($input, new BufferedOutput());
    }

    public function testCommandRemovesGuest(): void
    {
        $this->createGuest();

        static::assertCount(1, $this->fetchAllCustomers());

        $input = new ArrayInput(['type' => 'guests'], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertEmpty($this->fetchAllCustomers());
    }

    public function testCommandIsNoGuest(): void
    {
        $this->createGuest(false);

        static::assertCount(1, $this->fetchAllCustomers());

        $input = new ArrayInput(['type' => 'guests'], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(1, $this->fetchAllCustomers());
    }

    public function testCommandRemovesOnlyGuestsByDays(): void
    {
        $numberOfGuests = random_int(2, 6);
        $numberOfNoGuests = random_int(1, $numberOfGuests);

        for ($i = 0; $i < $numberOfGuests; ++$i) {
            $this->createGuest();
        }

        for ($i = 0; $i < $numberOfNoGuests; ++$i) {
            $this->createGuest(false);
        }

        $this->connection->executeStatement(
            'UPDATE customer set created_at = :createdAt where guest = true limit 1',
            ['createdAt' => (new \DateTime())->modify('-14 Day')->format(Defaults::STORAGE_DATE_TIME_FORMAT)]
        );

        $input = new ArrayInput(['type' => 'guests', '--days' => 14], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(($numberOfGuests + $numberOfNoGuests - 1), $this->fetchAllCustomers());

        $input = new ArrayInput(['type' => 'guests'], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount($numberOfNoGuests, $this->fetchAllCustomers());
    }

    public function testCommandRemovesNoGuestBecauseOfOrder(): void
    {
        $id = $this->createGuest();
        $this->createOrder($id);

        static::assertCount(1, $this->fetchAllCustomers());

        $input = new ArrayInput(['type' => 'guests'], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(1, $this->fetchAllCustomers());
    }

    public function testCommandRemovesMultipleGuests(): void
    {
        $numberOfGuests = random_int(2, 5);

        for ($i = 0; $i < $numberOfGuests; ++$i) {
            $this->createGuest();
        }

        static::assertCount($numberOfGuests, $this->fetchAllCustomers());

        $input = new ArrayInput(['type' => 'guests'], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertEmpty($this->fetchAllCustomers());
    }

    public function testCommandRemovesNoGuestBecauseOfDays(): void
    {
        $this->createGuest();

        static::assertCount(1, $this->fetchAllCustomers());

        $input = new ArrayInput(['type' => 'guests', '--days' => 5], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(1, $this->fetchAllCustomers());
    }

    public function testCommandRemovesAll(): void
    {
        $this->createGuest();
        $this->createCartWithCreatedAtDateTime(new \DateTime());

        static::assertCount(1, $this->fetchAllCustomers());
        static::assertCount(1, $this->fetchAllCarts());

        $input = new ArrayInput(['--all' => true], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertEmpty($this->fetchAllCustomers());
        static::assertEmpty($this->fetchAllCarts());
    }

    public function testCommandRemovesCart(): void
    {
        $this->createCartWithCreatedAtDateTime(new \Datetime());

        static::assertCount(1, $this->fetchAllCarts());

        $input = new ArrayInput(['type' => 'carts'], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(0, $this->fetchAllCarts());
    }

    public function testCommandRemovesMultipleCarts(): void
    {
        $numberOfCarts = random_int(2, 5);

        for ($i = 0; $i < $numberOfCarts; ++$i) {
            $this->createCartWithCreatedAtDateTime(new \Datetime());
        }

        static::assertCount($numberOfCarts, $this->fetchAllCarts());

        $input = new ArrayInput(['type' => 'carts'], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(0, $this->fetchAllCarts());
    }

    public function testCommandRemovesNoCartBecauseOfDays(): void
    {
        $this->createCartWithCreatedAtDateTime(new \Datetime());

        static::assertCount(1, $this->fetchAllCarts());

        $input = new ArrayInput(['type' => 'carts', '--days' => 5], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(1, $this->fetchAllCarts());
    }

    public function testCommandRemovesCartBecauseOfDays(): void
    {
        $this->createCartWithCreatedAtDateTime(new \Datetime());
        $this->createCartWithCreatedAtDateTime(new \Datetime('2018-10-10'));

        static::assertCount(2, $this->fetchAllCarts());

        $input = new ArrayInput(['type' => 'carts', '--days' => 5], $this->createInputDefinition());
        $this->getCommand()->run($input, new BufferedOutput());

        static::assertCount(1, $this->fetchAllCarts());
    }

    private function createOrder(
        string $customerId
    ): void {
        $orderId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $order = [
            'id' => $orderId,
            'itemRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'totalRounding' => json_decode(json_encode(new CashRoundingConfig(2, 0.01, true), \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR),
            'orderDateTime' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'price' => new CartPrice(10, 10, 10, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_NET),
            'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
            'orderCustomer' => [
                'customerId' => $customerId,
                'email' => 'test@example.com',
                'salutationId' => $this->fetchFirstIdFromTable('salutation'),
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
            ],
            'stateId' => $this->fetchFirstIdFromTable('state_machine'),
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

        $this->getContainer()->get('order.repository')->upsert([$order], Context::createDefaultContext());
    }

    private function clearTable(string $table): void
    {
        $this->connection->executeStatement('DELETE FROM ' . $table);
    }

    private function createGuest(bool $isGuest = true): string
    {
        $id = Uuid::randomHex();
        $salutation = $this->fetchFirstIdFromTable('salutation');

        $address = [
            'firstName' => 'not',
            'lastName' => 'not',
            'city' => 'not',
            'street' => 'not',
            'zipcode' => 'not',
            'salutationId' => $salutation,
            'country' => ['name' => 'not'],
        ];

        $guest = [
            'id' => $id,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultShippingAddress' => $address,
            'defaultPaymentMethodId' => $this->fetchFirstIdFromTable('payment_method'),
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'email' => Uuid::randomHex() . '@example.com',
            'lastName' => 'not',
            'firstName' => Random::getAlphanumericString(20),
            'salutationId' => $salutation,
            'customerNumber' => 'not',
            'guest' => $isGuest,
        ];

        $this->customerRepository->upsert([$guest], Context::createDefaultContext());

        return $id;
    }

    private function createCartWithCreatedAtDateTime(\DateTime $dateTime): void
    {
        $cartData = [
            'token' => Uuid::randomHex(),
            'payload' => '',
            'price' => 0,
            'line_item_count' => '',
            'rule_ids' => json_encode([]),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'shipping_method_id' => Uuid::fromHexToBytes($this->fetchFirstIdFromTable('shipping_method')),
            'payment_method_id' => Uuid::fromHexToBytes($this->fetchFirstIdFromTable('payment_method')),
            'country_id' => Uuid::fromHexToBytes($this->fetchFirstIdFromTable('country')),
            'sales_channel_id' => Uuid::fromHexToBytes(TestDefaults::SALES_CHANNEL),
            'created_at' => $dateTime->format('Y-m-d H:i:s'),
        ];

        $this->connection->insert('cart', $cartData);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllCustomers(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM customer');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchAllCarts(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM cart');
    }

    private function createInputDefinition(): InputDefinition
    {
        $type = new InputArgument('type', InputArgument::OPTIONAL);
        $days = new InputOption('days', null, InputOption::VALUE_REQUIRED);
        $all = new InputOption('all', null, InputOption::VALUE_NONE);

        return new InputDefinition([$type, $days, $all]);
    }

    private function fetchFirstIdFromTable(string $table): string
    {
        return Uuid::fromBytesToHex((string) $this->connection->fetchOne('SELECT id FROM ' . $table . ' LIMIT 1'));
    }

    private function getCommand(): CleanPersonalDataCommand
    {
        return new CleanPersonalDataCommand($this->connection, $this->customerRepository);
    }

    private function getArrayInput(): ArrayInput
    {
        $inputArgument = new InputArgument('types', InputArgument::IS_ARRAY);
        $inputOption = new InputOption('days', null, InputOption::VALUE_REQUIRED);
        $inputDefinition = new InputDefinition([$inputArgument, $inputOption]);

        return new ArrayInput([], $inputDefinition);
    }
}
