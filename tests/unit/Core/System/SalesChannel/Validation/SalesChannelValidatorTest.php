<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Validation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\Validation\SalesChannelValidator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelValidator::class)]
class SalesChannelValidatorTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionRegistry;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [SalesChannelDefinition::class, SalesChannelLanguageDefinition::class, SalesChannelCurrencyDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );
    }

    #[DataProvider('supportedSalesChannelTypeProvider')]
    public function testSupportedSalesChannelTypesRequireDefaultLanguageInLanguageList(string $typeId): void
    {
        $salesChannelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with(
                static::isString(),
                ['ids' => [Uuid::fromHexToBytes($salesChannelId)]],
                ['ids' => ArrayParameterType::BINARY]
            )
            ->willReturn([]);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand(
                    $this->definitionRegistry->getByEntityName(SalesChannelDefinition::ENTITY_NAME),
                    [
                        'type_id' => Uuid::fromHexToBytes($typeId),
                        'language_id' => Uuid::fromHexToBytes($languageId),
                    ],
                    ['id' => Uuid::fromHexToBytes($salesChannelId)],
                    static::createStub(EntityExistence::class),
                    '/0'
                ),
            ]
        );

        (new SalesChannelValidator($connection))->handleSalesChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__NO_GIVEN_DEFAULT_LANGUAGE_ID', $exception->getViolations()->get(0)->getCode());
    }

    public function testEverySalesChannelTypeRequiresDefaultLanguageInLanguageList(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand(
                    $this->definitionRegistry->getByEntityName(SalesChannelDefinition::ENTITY_NAME),
                    [
                        'type_id' => Uuid::randomBytes(),
                        'language_id' => Uuid::randomBytes(),
                    ],
                    ['id' => Uuid::randomBytes()],
                    static::createStub(EntityExistence::class),
                    '/0'
                ),
            ]
        );

        (new SalesChannelValidator($connection))->handleSalesChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__NO_GIVEN_DEFAULT_LANGUAGE_ID', $exception->getViolations()->get(0)->getCode());
    }

    public function testProductComparisonSalesChannelSucceedsWithDefaultLanguageInLanguageList(): void
    {
        $salesChannelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([]);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand(
                    $this->definitionRegistry->getByEntityName(SalesChannelDefinition::ENTITY_NAME),
                    [
                        'type_id' => Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON),
                        'language_id' => Uuid::fromHexToBytes($languageId),
                    ],
                    ['id' => Uuid::fromHexToBytes($salesChannelId)],
                    static::createStub(EntityExistence::class),
                    '/0'
                ),
                new InsertCommand(
                    $this->definitionRegistry->getByEntityName(SalesChannelLanguageDefinition::ENTITY_NAME),
                    [],
                    [
                        'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
                        'language_id' => Uuid::fromHexToBytes($languageId),
                    ],
                    static::createStub(EntityExistence::class),
                    '/0/languages/0'
                ),
            ]
        );

        (new SalesChannelValidator($connection))->handleSalesChannelLanguageIds($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testDeletingThePreviousDefaultLanguageSucceedsWhenTheSameWriteSetsANewDefault(): void
    {
        $salesChannelId = Uuid::randomHex();
        $previousDefaultId = Uuid::randomHex();
        $newDefaultId = Uuid::randomHex();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                $this->updateDefaultLanguageCommand($salesChannelId, $newDefaultId),
                $this->deleteLanguageCommand($salesChannelId, $previousDefaultId),
            ]
        );

        $connection = $this->connectionWithLanguageState($salesChannelId, $previousDefaultId, [$previousDefaultId, $newDefaultId]);

        (new SalesChannelValidator($connection))->handleSalesChannelLanguageIds($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testDeletingTheNewDefaultLanguageFails(): void
    {
        $salesChannelId = Uuid::randomHex();
        $previousDefaultId = Uuid::randomHex();
        $newDefaultId = Uuid::randomHex();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                $this->updateDefaultLanguageCommand($salesChannelId, $newDefaultId),
                $this->deleteLanguageCommand($salesChannelId, $newDefaultId),
            ]
        );

        $connection = $this->connectionWithLanguageState($salesChannelId, $previousDefaultId, [$previousDefaultId, $newDefaultId]);

        (new SalesChannelValidator($connection))->handleSalesChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__CANNOT_DELETE_DEFAULT_LANGUAGE_ID', $exception->getViolations()->get(0)->getCode());
    }

    public function testDeletingTheDefaultLanguageFailsWhenTheWriteKeepsThatDefault(): void
    {
        $salesChannelId = Uuid::randomHex();
        $defaultId = Uuid::randomHex();
        $secondLanguageId = Uuid::randomHex();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                $this->deleteLanguageCommand($salesChannelId, $defaultId),
            ]
        );

        $connection = $this->connectionWithLanguageState($salesChannelId, $defaultId, [$defaultId, $secondLanguageId]);

        (new SalesChannelValidator($connection))->handleSalesChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__CANNOT_DELETE_DEFAULT_LANGUAGE_ID', $exception->getViolations()->get(0)->getCode());
    }

    public function testSalesChannelRequiresDefaultCurrencyInCurrencyList(): void
    {
        $salesChannelId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new InsertCommand(
                    $this->definitionRegistry->getByEntityName(SalesChannelDefinition::ENTITY_NAME),
                    ['currency_id' => Uuid::fromHexToBytes($currencyId)],
                    ['id' => Uuid::fromHexToBytes($salesChannelId)],
                    static::createStub(EntityExistence::class),
                    '/0'
                ),
            ]
        );

        (new SalesChannelValidator($connection))->handleSalesChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__NO_GIVEN_DEFAULT_CURRENCY_ID', $exception->getViolations()->get(0)->getCode());
    }

    public function testUpdatingDefaultCurrencyToUnassignedCurrencyFails(): void
    {
        $salesChannelId = Uuid::randomHex();
        $currentDefaultId = Uuid::randomHex();
        $newDefaultId = Uuid::randomHex();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new UpdateCommand(
                    $this->definitionRegistry->getByEntityName(SalesChannelDefinition::ENTITY_NAME),
                    ['currency_id' => Uuid::fromHexToBytes($newDefaultId)],
                    ['id' => Uuid::fromHexToBytes($salesChannelId)],
                    static::createStub(EntityExistence::class),
                    '/0'
                ),
            ]
        );

        $connection = $this->connectionWithCurrencyState($salesChannelId, $currentDefaultId, [$currentDefaultId]);

        (new SalesChannelValidator($connection))->handleSalesChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__CANNOT_UPDATE_DEFAULT_CURRENCY_ID', $exception->getViolations()->get(0)->getCode());
    }

    public function testDeletingDefaultCurrencyFails(): void
    {
        $salesChannelId = Uuid::randomHex();
        $defaultId = Uuid::randomHex();

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $this->definitionRegistry->getByEntityName(SalesChannelCurrencyDefinition::ENTITY_NAME),
                    [
                        'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
                        'currency_id' => Uuid::fromHexToBytes($defaultId),
                    ],
                    static::createStub(EntityExistence::class)
                ),
            ]
        );

        $connection = $this->connectionWithCurrencyState($salesChannelId, $defaultId, [$defaultId]);

        (new SalesChannelValidator($connection))->handleSalesChannelLanguageIds($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame('SYSTEM__CANNOT_DELETE_DEFAULT_CURRENCY_ID', $exception->getViolations()->get(0)->getCode());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function supportedSalesChannelTypeProvider(): iterable
    {
        yield 'storefront' => [Defaults::SALES_CHANNEL_TYPE_STOREFRONT];
        yield 'api' => [Defaults::SALES_CHANNEL_TYPE_API];
        yield 'product comparison' => [Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON];
        yield 'agentic commerce' => [Defaults::SALES_CHANNEL_TYPE_AGENTIC_COMMERCE];
    }

    private function updateDefaultLanguageCommand(string $salesChannelId, string $languageId): UpdateCommand
    {
        return new UpdateCommand(
            $this->definitionRegistry->getByEntityName(SalesChannelDefinition::ENTITY_NAME),
            ['language_id' => Uuid::fromHexToBytes($languageId)],
            ['id' => Uuid::fromHexToBytes($salesChannelId)],
            static::createStub(EntityExistence::class),
            '/0'
        );
    }

    private function deleteLanguageCommand(string $salesChannelId, string $languageId): DeleteCommand
    {
        return new DeleteCommand(
            $this->definitionRegistry->getByEntityName(SalesChannelLanguageDefinition::ENTITY_NAME),
            [
                'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
                'language_id' => Uuid::fromHexToBytes($languageId),
            ],
            static::createStub(EntityExistence::class)
        );
    }

    /**
     * @param list<string> $assignedLanguageIds
     */
    private function connectionWithLanguageState(string $salesChannelId, string $currentDefaultId, array $assignedLanguageIds): Connection
    {
        $states = [];
        foreach ($assignedLanguageIds as $languageId) {
            $states[] = [
                'sales_channel_id' => $salesChannelId,
                'current_default' => $currentDefaultId,
                'language_id' => $languageId,
            ];
        }

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($states);

        return $connection;
    }

    /**
     * @param list<string> $assignedCurrencyIds
     */
    private function connectionWithCurrencyState(string $salesChannelId, string $currentDefaultId, array $assignedCurrencyIds): Connection
    {
        $states = [];
        foreach ($assignedCurrencyIds as $currencyId) {
            $states[] = [
                'sales_channel_id' => $salesChannelId,
                'current_default' => $currentDefaultId,
                'currency_id' => $currencyId,
            ];
        }

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($states);

        return $connection;
    }
}
