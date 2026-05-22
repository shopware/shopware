<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Validation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
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
            [SalesChannelDefinition::class, SalesChannelLanguageDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
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
                    $this->createMock(EntityExistence::class),
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

    public function testUnsupportedSalesChannelTypeDoesNotRequireDefaultLanguageInLanguageList(): void
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
                    $this->createMock(EntityExistence::class),
                    '/0'
                ),
            ]
        );

        (new SalesChannelValidator($connection))->handleSalesChannelLanguageIds($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
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
                    $this->createMock(EntityExistence::class),
                    '/0'
                ),
                new InsertCommand(
                    $this->definitionRegistry->getByEntityName(SalesChannelLanguageDefinition::ENTITY_NAME),
                    [],
                    [
                        'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
                        'language_id' => Uuid::fromHexToBytes($languageId),
                    ],
                    $this->createMock(EntityExistence::class),
                    '/0/languages/0'
                ),
            ]
        );

        (new SalesChannelValidator($connection))->handleSalesChannelLanguageIds($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
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
}
