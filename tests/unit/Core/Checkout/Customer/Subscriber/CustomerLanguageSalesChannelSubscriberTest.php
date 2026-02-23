<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerLanguageSalesChannelSubscriber;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
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
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(CustomerLanguageSalesChannelSubscriber::class)]
#[Package('checkout')]
class CustomerLanguageSalesChannelSubscriberTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionRegistry;

    /**
     * @var MockObject&EntityRepository<EntityCollection<PartialEntity>>
     */
    private MockObject $salesChannelRepository;

    private CustomerLanguageSalesChannelSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [CustomerDefinition::class, TaxDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
        $this->salesChannelRepository = $this->createMock(EntityRepository::class);
        $this->subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'validate'],
            CustomerLanguageSalesChannelSubscriber::getSubscribedEvents()
        );
    }

    public function testValidateSkipsWhenSalesChannelApiSource(): void
    {
        $context = new Context(new SalesChannelApiSource(Uuid::randomHex()));
        $event = new PreWriteValidationEvent(WriteContext::createFromContext($context), []);

        $this->salesChannelRepository->expects($this->never())->method('search');

        $this->subscriber->validate($event);
    }

    public function testValidateSkipsWhenNoCustomerCommands(): void
    {
        $context = Context::createDefaultContext();
        $event = new PreWriteValidationEvent(WriteContext::createFromContext($context), []);

        $this->salesChannelRepository->expects($this->never())->method('search');

        $this->subscriber->validate($event);
    }

    public function testValidateSkipsWhenCustomerCommandHasNoLanguageId(): void
    {
        $context = Context::createDefaultContext();
        $customerDef = $this->definitionRegistry->get(CustomerDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $customerDef,
                    ['sales_channel_id' => Uuid::randomBytes()],
                    ['id' => Uuid::randomBytes()],
                    $this->createMock(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $this->salesChannelRepository->expects($this->never())->method('search');

        $this->subscriber->validate($event);
    }

    public function testValidateSkipsWhenInsertHasNoSalesChannelIdAndNoCustomerId(): void
    {
        $languageIdBytes = Uuid::fromHexToBytes(Uuid::randomHex());
        $context = Context::createDefaultContext();
        $customerDef = $this->definitionRegistry->get(CustomerDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $customerDef,
                    ['language_id' => $languageIdBytes],
                    ['id' => Uuid::randomBytes()],
                    $this->createMock(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $this->salesChannelRepository->expects($this->never())->method('search');

        $this->subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateSkipsWhenLanguageInSalesChannel(): void
    {
        $salesChannelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $salesChannelIdBytes = Uuid::fromHexToBytes($salesChannelId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $customerDef = $this->definitionRegistry->get(CustomerDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $customerDef,
                    [
                        'language_id' => $languageIdBytes,
                        'sales_channel_id' => $salesChannelIdBytes,
                    ],
                    ['id' => Uuid::randomBytes()],
                    $this->createMock(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $language = new PartialEntity(['id' => $languageId]);
        $salesChannel = new PartialEntity([
            'id' => $salesChannelId,
            'languages' => new EntityCollection([$language]),
        ]);
        $salesChannels = new EntityCollection([$salesChannel]);
        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $context)
            ->willReturn(new EntitySearchResult('sales_channel', 1, $salesChannels, null, new Criteria(), $context));

        $this->subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateAddsViolationWhenLanguageNotInSalesChannel(): void
    {
        $salesChannelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $salesChannelIdBytes = Uuid::fromHexToBytes($salesChannelId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $customerDef = $this->definitionRegistry->get(CustomerDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $customerDef,
                    [
                        'language_id' => $languageIdBytes,
                        'sales_channel_id' => $salesChannelIdBytes,
                    ],
                    ['id' => Uuid::randomBytes()],
                    $this->createMock(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $salesChannel = new PartialEntity([
            'id' => $salesChannelId,
            'languages' => new EntityCollection([]),
        ]);
        $salesChannels = new EntityCollection([$salesChannel]);
        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $context)
            ->willReturn(new EntitySearchResult('sales_channel', 1, $salesChannels, null, new Criteria(), $context));

        $this->subscriber->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertCount(1, $exception->getViolations());
        static::assertSame(
            CustomerLanguageSalesChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL,
            $exception->getViolations()->get(0)->getCode()
        );
    }

    public function testValidateAddsViolationWhenSalesChannelNotInSearchResult(): void
    {
        $salesChannelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $salesChannelIdBytes = Uuid::fromHexToBytes($salesChannelId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $customerDef = $this->definitionRegistry->get(CustomerDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $customerDef,
                    [
                        'language_id' => $languageIdBytes,
                        'sales_channel_id' => $salesChannelIdBytes,
                    ],
                    ['id' => Uuid::randomBytes()],
                    $this->createMock(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $context)
            ->willReturn(new EntitySearchResult('sales_channel', 0, new EntityCollection(), null, new Criteria(), $context));

        $this->subscriber->validate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        $exception = $exceptions[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame(
            CustomerLanguageSalesChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL,
            $exception->getViolations()->get(0)->getCode()
        );
    }

    public function testValidateUpdateFindsSalesChannelByCustomerIdWhenSalesChannelIdNull(): void
    {
        $customerId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $customerIdBytes = Uuid::fromHexToBytes($customerId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $customerDef = $this->definitionRegistry->get(CustomerDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new UpdateCommand(
                    $customerDef,
                    ['language_id' => $languageIdBytes],
                    ['id' => $customerIdBytes],
                    $this->createMock(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $customerRef = new PartialEntity(['id' => $customerId]);
        $language = new PartialEntity(['id' => $languageId]);
        $salesChannel = new PartialEntity([
            'id' => $salesChannelId,
            'languages' => new EntityCollection([$language]),
            'customers' => new EntityCollection([$customerRef]),
        ]);
        $salesChannels = new EntityCollection([$salesChannel]);
        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $context)
            ->willReturn(new EntitySearchResult('sales_channel', 1, $salesChannels, null, new Criteria(), $context));

        $this->subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateUpdateAddsViolationWhenLanguageNotInSalesChannelResolvedByCustomer(): void
    {
        $customerId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $customerIdBytes = Uuid::fromHexToBytes($customerId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $customerDef = $this->definitionRegistry->get(CustomerDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new UpdateCommand(
                    $customerDef,
                    ['language_id' => $languageIdBytes],
                    ['id' => $customerIdBytes],
                    $this->createMock(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $customerRef = new PartialEntity(['id' => $customerId]);
        $salesChannel = new PartialEntity([
            'id' => $salesChannelId,
            'languages' => new EntityCollection([]),
            'customers' => new EntityCollection([$customerRef]),
        ]);
        $salesChannels = new EntityCollection([$salesChannel]);
        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->with(static::isInstanceOf(Criteria::class), $context)
            ->willReturn(new EntitySearchResult('sales_channel', 1, $salesChannels, null, new Criteria(), $context));

        $this->subscriber->validate($event);

        static::assertCount(1, $event->getExceptions()->getExceptions());
        $exception = $event->getExceptions()->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $exception);
        static::assertSame(
            CustomerLanguageSalesChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL,
            $exception->getViolations()->get(0)->getCode()
        );
    }

    public function testValidateSkipsCommandsForOtherEntities(): void
    {
        $salesChannelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $customerDef = $this->definitionRegistry->get(CustomerDefinition::class);
        $taxDef = $this->definitionRegistry->get(TaxDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new InsertCommand(
                    $taxDef,
                    ['name' => 'test', 'tax_rate' => 19.0, 'position' => 1],
                    ['id' => Uuid::randomBytes()],
                    $this->createMock(EntityExistence::class),
                    '/0/'
                ),
                new InsertCommand(
                    $customerDef,
                    [
                        'language_id' => Uuid::fromHexToBytes($languageId),
                        'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
                    ],
                    ['id' => Uuid::randomBytes()],
                    $this->createMock(EntityExistence::class),
                    '/1/'
                ),
            ]
        );

        $language = new PartialEntity(['id' => $languageId]);
        $salesChannel = new PartialEntity([
            'id' => $salesChannelId,
            'languages' => new EntityCollection([$language]),
        ]);
        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult('sales_channel', 1, new EntityCollection([$salesChannel]), null, new Criteria(), $context));

        $this->subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateSkipsDeleteCommandForCustomer(): void
    {
        $salesChannelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $context = Context::createDefaultContext();
        $customerDef = $this->definitionRegistry->get(CustomerDefinition::class);
        $customerIdBytes = Uuid::randomBytes();
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new DeleteCommand(
                    $customerDef,
                    ['id' => $customerIdBytes],
                    EntityExistence::createForEntity(CustomerDefinition::ENTITY_NAME, ['id' => $customerIdBytes])
                ),
                new InsertCommand(
                    $customerDef,
                    [
                        'language_id' => Uuid::fromHexToBytes($languageId),
                        'sales_channel_id' => Uuid::fromHexToBytes($salesChannelId),
                    ],
                    ['id' => Uuid::randomBytes()],
                    $this->createMock(EntityExistence::class),
                    '/1/'
                ),
            ]
        );

        $language = new PartialEntity(['id' => $languageId]);
        $salesChannel = new PartialEntity([
            'id' => $salesChannelId,
            'languages' => new EntityCollection([$language]),
        ]);
        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult('sales_channel', 1, new EntityCollection([$salesChannel]), null, new Criteria(), $context));

        $this->subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateSkipsWhenCustomerNotInAnyFetchedSalesChannel(): void
    {
        $customerId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $customerIdBytes = Uuid::fromHexToBytes($customerId);
        $languageIdBytes = Uuid::fromHexToBytes($languageId);
        $context = Context::createDefaultContext();
        $customerDef = $this->definitionRegistry->get(CustomerDefinition::class);
        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new UpdateCommand(
                    $customerDef,
                    ['language_id' => $languageIdBytes],
                    ['id' => $customerIdBytes],
                    $this->createMock(EntityExistence::class),
                    '/0/'
                ),
            ]
        );

        $otherCustomerId = Uuid::randomHex();
        $salesChannel = new PartialEntity([
            'id' => Uuid::randomHex(),
            'languages' => new EntityCollection([new PartialEntity(['id' => $languageId])]),
            'customers' => new EntityCollection([new PartialEntity(['id' => $otherCustomerId])]),
        ]);
        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult('sales_channel', 1, new EntityCollection([$salesChannel]), null, new Criteria(), $context));

        $this->subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }
}
