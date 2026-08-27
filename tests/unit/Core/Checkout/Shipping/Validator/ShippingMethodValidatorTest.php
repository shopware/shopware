<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Shipping\Validator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\Validator\ShippingMethodValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Doctrine\FakeConnection;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShippingMethodValidator::class)]
class ShippingMethodValidatorTest extends TestCase
{
    private const CURRENCY_PRICE = '{"cb7d2554b0ce847cd82f3ac9bd1c0dfca":{"net":10.0,"gross":11.0,"linked":false}}';

    private WriteContext $context;

    private ShippingMethodDefinition $shippingMethodDefinition;

    private PaymentMethodDefinition $paymentMethodDefinition;

    private ShippingMethodPriceDefinition $shippingMethodPriceDefinition;

    private string $priceId;

    protected function setUp(): void
    {
        $this->context = WriteContext::createFromContext(Context::createDefaultContext());
        $this->priceId = Uuid::randomHex();

        $registry = new StaticDefinitionInstanceRegistry(
            [ShippingMethodDefinition::class, PaymentMethodDefinition::class, ShippingMethodPriceDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $definition = $registry->get(ShippingMethodDefinition::class);
        static::assertInstanceOf(ShippingMethodDefinition::class, $definition);
        $this->shippingMethodDefinition = $definition;

        $definition = $registry->get(PaymentMethodDefinition::class);
        static::assertInstanceOf(PaymentMethodDefinition::class, $definition);
        $this->paymentMethodDefinition = $definition;

        $definition = $registry->get(ShippingMethodPriceDefinition::class);
        static::assertInstanceOf(ShippingMethodPriceDefinition::class, $definition);
        $this->shippingMethodPriceDefinition = $definition;
    }

    public function testSubscribedEvents(): void
    {
        $events = ShippingMethodValidator::getSubscribedEvents();

        static::assertCount(1, $events);
        static::assertEquals('preValidate', $events[PreWriteValidationEvent::class]);
    }

    public function testPreValidateWithInvalidCommands(): void
    {
        $commands = [];
        $commands[] = new UpdateCommand($this->paymentMethodDefinition, [], ['id' => Uuid::randomBytes()], EntityExistence::createForEntity('shipping_method', ['id' => Uuid::randomBytes()]), '/0/');
        $commands[] = new class($this->shippingMethodDefinition, [], [], EntityExistence::createForEntity('shipping_method', ['id' => Uuid::randomBytes()]), '/0/') extends WriteCommand {
            public function getPrivilege(): ?string
            {
                return null;
            }
        };

        $fakeConnection = new FakeConnection([]);

        $event = new PreWriteValidationEvent($this->context, $commands);
        $validator = new ShippingMethodValidator($fakeConnection);
        $validator->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    #[DataProvider('shippingMethodTaxProvider')]
    public function testShippingMethodValidator(?string $taxType, ?string $taxId, bool $success): void
    {
        $commands = [];
        $commands[] = new InsertCommand(
            $this->shippingMethodDefinition,
            [
                'name' => 'test',
                'tax_type' => $taxType,
                'tax_id' => $taxId,
                'availability_rule' => [
                    'id' => Uuid::randomBytes(),
                    'name' => 'asd',
                    'priority' => 2,
                ],
            ],
            ['id' => Uuid::randomBytes()],
            $this->createMock(EntityExistence::class),
            '/0/'
        );

        $fakeConnection = new FakeConnection([]);

        $event = new PreWriteValidationEvent($this->context, $commands);
        $validator = new ShippingMethodValidator($fakeConnection);
        $validator->preValidate($event);

        $exception = null;

        try {
            $event->getExceptions()->tryToThrow();
        } catch (WriteException $e) {
            $exception = $e;
        }

        if (!$success) {
            static::assertNotNull($exception);
            static::assertEquals(WriteConstraintViolationException::class, $exception->getExceptions()[0]::class);
        } else {
            static::assertNull($exception);
        }
    }

    public static function shippingMethodTaxProvider(): \Generator
    {
        yield 'Test tax type is null' => [null, null, true];
        yield 'Test tax type is invalid' => ['invalid', null, false];
        yield 'Test tax type is auto' => ['auto', null, true];
        yield 'Test tax type is highest' => ['highest', null, true];
        yield 'Test tax type is fixed without tax ID' => ['fixed', null, false];
        yield 'Test tax type is fixed with tax ID' => ['fixed', Uuid::randomBytes(), true];
    }

    public function testDeletingTheLastPriceOfAnActiveShippingMethodIsRejected(): void
    {
        $shippingMethodId = Uuid::randomHex();

        $violation = $this->validatePrices(
            [$this->deletePriceCommand()],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
        );

        static::assertNotNull($violation);
        static::assertSame(ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE, $violation->getCode());
        static::assertSame('/prices', $violation->getPropertyPath());
    }

    public function testDeletingOneOfSeveralPricesIsAllowed(): void
    {
        $shippingMethodId = Uuid::randomHex();

        static::assertNull($this->validatePrices(
            [$this->deletePriceCommand()],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 2)],
        ));
    }

    public function testDeletingTheLastPriceOfAnInactiveShippingMethodIsAllowed(): void
    {
        $shippingMethodId = Uuid::randomHex();

        static::assertNull($this->validatePrices(
            [$this->deletePriceCommand()],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: false, priceCount: 1)],
        ));
    }

    public function testAPriceAddedInTheSameWriteOffsetsTheDeletedOne(): void
    {
        $shippingMethodId = Uuid::randomHex();

        static::assertNull($this->validatePrices(
            [
                $this->deletePriceCommand(),
                $this->insertPriceCommand($shippingMethodId),
            ],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
        ));
    }

    public function testUpdatingAPriceDoesNotOffsetTheDeletedOne(): void
    {
        $shippingMethodId = Uuid::randomHex();

        $violation = $this->validatePrices(
            [
                $this->deletePriceCommand(),
                new UpdateCommand(
                    $this->shippingMethodPriceDefinition,
                    ['quantity_start' => 5],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
            ],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
        );

        static::assertNotNull($violation);
        static::assertSame(ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE, $violation->getCode());
    }

    public function testMovingTheLastPriceAwayFromAnActiveShippingMethodIsRejected(): void
    {
        $shippingMethodId = Uuid::randomHex();

        $violation = $this->validatePrices(
            [$this->updatePriceCommand($this->priceId, [
                'shipping_method_id' => Uuid::randomBytes(),
            ])],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
        );

        static::assertNotNull($violation);
        static::assertSame(ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE, $violation->getCode());
    }

    public function testKeepingAPriceOnTheSameShippingMethodIsAllowed(): void
    {
        $shippingMethodId = Uuid::randomHex();

        static::assertNull($this->validatePrices(
            [$this->updatePriceCommand($this->priceId, [
                'shipping_method_id' => Uuid::fromHexToBytes($shippingMethodId),
            ])],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
        ));
    }

    public function testPriceInsertedAndDeletedInTheSameWriteDoesNotOffsetADeletion(): void
    {
        $shippingMethodId = Uuid::randomHex();
        $temporaryPriceId = Uuid::randomHex();

        $violation = $this->validatePrices(
            [
                $this->insertPriceCommand($shippingMethodId, $temporaryPriceId),
                $this->deletePriceCommand(),
                $this->deletePriceCommand($temporaryPriceId),
            ],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
        );

        static::assertNotNull($violation);
        static::assertSame(ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE, $violation->getCode());
    }

    public function testUpdatingADeletedPriceDoesNotRecreateIt(): void
    {
        $shippingMethodId = Uuid::randomHex();

        $violation = $this->validatePrices(
            [
                $this->deletePriceCommand(),
                $this->updatePriceCommand($this->priceId, [
                    'shipping_method_id' => Uuid::fromHexToBytes($shippingMethodId),
                ]),
            ],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
        );

        static::assertNotNull($violation);
        static::assertSame(ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE, $violation->getCode());
    }

    public function testAnInsertedPriceWithoutAShippingMethodIsIgnored(): void
    {
        $shippingMethodId = Uuid::randomHex();

        $violation = $this->validatePrices(
            [
                $this->deletePriceCommand(),
                new InsertCommand(
                    $this->shippingMethodPriceDefinition,
                    ['quantity_start' => 0],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/0/'
                ),
            ],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
        );

        static::assertNotNull($violation);
        static::assertSame(ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE, $violation->getCode());
    }

    public function testActivatingAShippingMethodWithoutPricesIsRejected(): void
    {
        $shippingMethodId = Uuid::randomHex();

        $violation = $this->validatePrices(
            [$this->updateActiveCommand($shippingMethodId, active: true)],
            priceOwners: [],
            states: [$this->state($shippingMethodId, active: false, priceCount: 0)],
        );

        static::assertNotNull($violation);
        static::assertSame(ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE, $violation->getCode());
    }

    public function testActivatingAShippingMethodWithPricesIsAllowed(): void
    {
        $shippingMethodId = Uuid::randomHex();

        static::assertNull($this->validatePrices(
            [$this->updateActiveCommand($shippingMethodId, active: true)],
            priceOwners: [],
            states: [$this->state($shippingMethodId, active: false, priceCount: 1)],
        ));
    }

    public function testDeactivatingAPricelessShippingMethodIsAllowed(): void
    {
        $shippingMethodId = Uuid::randomHex();

        static::assertNull($this->validatePrices(
            [$this->updateActiveCommand($shippingMethodId, active: false)],
            priceOwners: [],
            states: [$this->state($shippingMethodId, active: true, priceCount: 0)],
        ));
    }

    public function testDeactivatingWhileDeletingTheLastPriceIsAllowed(): void
    {
        $shippingMethodId = Uuid::randomHex();

        static::assertNull($this->validatePrices(
            [
                $this->updateActiveCommand($shippingMethodId, active: false),
                $this->deletePriceCommand(),
            ],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
        ));
    }

    public function testAnUpdateThatLeavesActiveUntouchedIsNotChecked(): void
    {
        $shippingMethodId = Uuid::randomHex();

        static::assertNull($this->validatePrices(
            [new UpdateCommand(
                $this->shippingMethodDefinition,
                ['name' => 'renamed'],
                ['id' => Uuid::fromHexToBytes($shippingMethodId)],
                static::createStub(EntityExistence::class),
                '/0/'
            )],
            priceOwners: [],
            states: [$this->state($shippingMethodId, active: true, priceCount: 0)],
        ));
    }

    public function testDeletingTheShippingMethodItselfIsAllowed(): void
    {
        $shippingMethodId = Uuid::randomHex();

        static::assertNull($this->validatePrices(
            [
                new DeleteCommand(
                    $this->shippingMethodDefinition,
                    ['id' => Uuid::fromHexToBytes($shippingMethodId)],
                    static::createStub(EntityExistence::class)
                ),
                $this->deletePriceCommand(),
            ],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
        ));
    }

    public function testCreatingAnActiveShippingMethodWithoutPricesIsAllowed(): void
    {
        static::assertNull($this->validatePrices(
            [new InsertCommand(
                $this->shippingMethodDefinition,
                ['active' => 1],
                ['id' => Uuid::randomBytes()],
                static::createStub(EntityExistence::class),
                '/0/'
            )],
            priceOwners: [],
            states: [],
        ));
    }

    public function testDeletingTheLastResolvingPriceIsRejectedWhileAPriceWithoutCurrencyValuesRemains(): void
    {
        $shippingMethodId = Uuid::randomHex();
        $emptyPriceId = Uuid::randomHex();

        $violation = $this->validatePrices(
            [$this->deletePriceCommand()],
            priceOwners: [$this->priceId => $shippingMethodId, $emptyPriceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
            pricesWithoutCurrencyValues: [$emptyPriceId],
        );

        static::assertNotNull($violation);
        static::assertSame(ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE, $violation->getCode());
    }

    public function testDeletingAPriceWithoutCurrencyValuesDoesNotChangeTheCount(): void
    {
        $shippingMethodId = Uuid::randomHex();

        static::assertNull($this->validatePrices(
            [$this->deletePriceCommand()],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
            pricesWithoutCurrencyValues: [$this->priceId],
        ));
    }

    public function testClearingTheCurrencyValuesOfTheLastPriceIsRejected(): void
    {
        $shippingMethodId = Uuid::randomHex();

        $violation = $this->validatePrices(
            [$this->updatePriceCommand($this->priceId, ['currency_price' => null])],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
        );

        static::assertNotNull($violation);
        static::assertSame(ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE, $violation->getCode());
    }

    public function testGivingCurrencyValuesToAPriceOffsetsTheDeletedOne(): void
    {
        $shippingMethodId = Uuid::randomHex();
        $emptyPriceId = Uuid::randomHex();

        static::assertNull($this->validatePrices(
            [
                $this->deletePriceCommand(),
                $this->updatePriceCommand($emptyPriceId, ['currency_price' => self::CURRENCY_PRICE]),
            ],
            priceOwners: [$this->priceId => $shippingMethodId, $emptyPriceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
            pricesWithoutCurrencyValues: [$emptyPriceId],
        ));
    }

    public function testActivatingAShippingMethodWhoseOnlyPriceHasNoCurrencyValuesIsRejected(): void
    {
        $shippingMethodId = Uuid::randomHex();

        $violation = $this->validatePrices(
            [$this->updateActiveCommand($shippingMethodId, active: true)],
            priceOwners: [],
            states: [$this->state($shippingMethodId, active: false, priceCount: 0)],
        );

        static::assertNotNull($violation);
        static::assertSame(ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE, $violation->getCode());
    }

    public function testAPriceCommandWithoutAnIdIsIgnored(): void
    {
        $shippingMethodId = Uuid::randomHex();

        $violation = $this->validatePrices(
            [
                $this->deletePriceCommand(),
                new DeleteCommand($this->shippingMethodPriceDefinition, [], static::createStub(EntityExistence::class)),
            ],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
        );

        static::assertNotNull($violation);
        static::assertSame(ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE, $violation->getCode());
    }

    public function testAPriceRowWithoutUsableIdsIsSkipped(): void
    {
        $shippingMethodId = Uuid::randomHex();

        $violation = $this->validatePrices(
            [$this->deletePriceCommand()],
            priceOwners: [$this->priceId => $shippingMethodId],
            states: [$this->state($shippingMethodId, active: true, priceCount: 1)],
            withMalformedPriceRow: true,
        );

        static::assertNotNull($violation);
        static::assertSame(ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE, $violation->getCode());
    }

    private function deletePriceCommand(?string $priceId = null): DeleteCommand
    {
        return new DeleteCommand(
            $this->shippingMethodPriceDefinition,
            ['id' => Uuid::fromHexToBytes($priceId ?? $this->priceId)],
            static::createStub(EntityExistence::class)
        );
    }

    private function insertPriceCommand(string $shippingMethodId, ?string $priceId = null): InsertCommand
    {
        return new InsertCommand(
            $this->shippingMethodPriceDefinition,
            ['shipping_method_id' => Uuid::fromHexToBytes($shippingMethodId), 'currency_price' => self::CURRENCY_PRICE],
            ['id' => Uuid::fromHexToBytes($priceId ?? Uuid::randomHex())],
            static::createStub(EntityExistence::class),
            '/0/'
        );
    }

    private function updateActiveCommand(string $shippingMethodId, bool $active): UpdateCommand
    {
        return new UpdateCommand(
            $this->shippingMethodDefinition,
            ['active' => $active ? 1 : 0],
            ['id' => Uuid::fromHexToBytes($shippingMethodId)],
            static::createStub(EntityExistence::class),
            '/0/'
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function updatePriceCommand(string $priceId, array $payload): UpdateCommand
    {
        return new UpdateCommand(
            $this->shippingMethodPriceDefinition,
            $payload,
            ['id' => Uuid::fromHexToBytes($priceId)],
            static::createStub(EntityExistence::class),
            '/0/'
        );
    }

    /**
     * @return array{id: string, active: int, price_count: int}
     */
    private function state(string $shippingMethodId, bool $active, int $priceCount): array
    {
        return ['id' => $shippingMethodId, 'active' => $active ? 1 : 0, 'price_count' => $priceCount];
    }

    /**
     * @param list<WriteCommand> $commands
     * @param array<string, string> $priceOwners shipping method id keyed by price id
     * @param list<array{id: string, active: int, price_count: int}> $states
     * @param list<string> $pricesWithoutCurrencyValues price ids that cannot resolve shipping costs
     */
    private function validatePrices(array $commands, array $priceOwners, array $states, array $pricesWithoutCurrencyValues = [], bool $withMalformedPriceRow = false): ?ConstraintViolationInterface
    {
        $connection = new ShippingMethodStateConnection([]);
        foreach ($priceOwners as $priceId => $shippingMethodId) {
            $usable = \in_array($priceId, $pricesWithoutCurrencyValues, true) ? 0 : 1;
            $connection->priceOwners[] = [$priceId, $shippingMethodId, $usable];
        }
        if ($withMalformedPriceRow) {
            $connection->priceOwners[] = [null, null, 1];
        }
        $connection->states = $states;

        $event = new PreWriteValidationEvent($this->context, $commands);

        (new ShippingMethodValidator($connection))->preValidate($event);

        foreach ($event->getExceptions()->getExceptions() as $exception) {
            if (!$exception instanceof WriteConstraintViolationException) {
                continue;
            }

            foreach ($exception->getViolations() as $violation) {
                if ($violation->getCode() === ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE) {
                    return $violation;
                }
            }
        }

        return null;
    }
}

/**
 * @internal
 */
class ShippingMethodStateConnection extends FakeConnection
{
    /**
     * @var list<array{string|null, string|null, int}>
     */
    public array $priceOwners = [];

    /**
     * @var list<array{id: string, active: int, price_count: int}>
     */
    public array $states = [];

    public function fetchAllNumeric(string $query, array $params = [], array $types = []): array
    {
        return $this->priceOwners;
    }

    public function fetchAllAssociative(string $query, array $params = [], array $types = []): array
    {
        return $this->states;
    }
}
