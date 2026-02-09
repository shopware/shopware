<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerLanguageSalesChannelSubscriber;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerLanguageSalesChannelSubscriber::class)]
class CustomerLanguageSalesChannelSubscriberTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $definitionRegistry;

    /**
     * @var MockObject&EntityRepository<SalesChannelCollection>
     */
    private MockObject&EntityRepository $salesChannelRepository;

    /**
     * @var MockObject&EntityRepository<CustomerCollection>
     */
    private MockObject&EntityRepository $customerRepository;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [CustomerDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
        $this->salesChannelRepository = $this->createMock(EntityRepository::class);
        $this->customerRepository = $this->createMock(EntityRepository::class);
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'validate'],
            CustomerLanguageSalesChannelSubscriber::getSubscribedEvents()
        );
    }

    public function testValidateSkipsWhenContextIsSalesChannelApiSource(): void
    {
        $context = new Context(new SalesChannelApiSource(Uuid::randomHex()));
        $writeContext = WriteContext::createFromContext($context);
        $event = new PreWriteValidationEvent($writeContext, []);

        $this->salesChannelRepository->expects($this->never())->method('search');

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository, $this->customerRepository);
        $subscriber->validate($event);
    }

    public function testValidateSkipsWhenNoCommands(): void
    {
        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $event = new PreWriteValidationEvent($writeContext, []);

        $this->salesChannelRepository->expects($this->never())->method('search');
        $this->customerRepository->expects($this->never())->method('search');

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository, $this->customerRepository);
        $subscriber->validate($event);
    }

    public function testValidateSkipsWhenCommandIsNotCustomerEntity(): void
    {
        $command = $this->createMock(WriteCommand::class);
        $command->method('getEntityName')->willReturn('product');

        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->salesChannelRepository->expects($this->never())->method('search');
        $this->customerRepository->expects($this->never())->method('search');

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository, $this->customerRepository);
        $subscriber->validate($event);
    }

    public function testValidateSkipsWhenCommandIsDeleteCommand(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $command = new DeleteCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            ['id' => $ids->getBytes('customer1')],
            new EntityExistence('customer', ['id' => $ids->getBytes('customer1')], true, false, false, [])
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->salesChannelRepository->expects($this->never())->method('search');
        $this->customerRepository->expects($this->never())->method('search');

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository, $this->customerRepository);
        $subscriber->validate($event);
    }

    public function testValidateSkipsWhenPayloadHasNoLanguageId(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $command = new InsertCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            ['sales_channel_id' => $ids->getBytes('sc1')],
            ['id' => $ids->getBytes('customer1')],
            $this->createMock(EntityExistence::class),
            '/0/'
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->salesChannelRepository->expects($this->never())->method('search');
        $this->customerRepository->expects($this->never())->method('search');

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository, $this->customerRepository);
        $subscriber->validate($event);
    }

    public function testValidateSkipsWhenLanguageIdOrSalesChannelIdNull(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $command = new UpdateCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            ['language_id' => $ids->getBytes('lang1')],
            ['id' => $ids->getBytes('customer1')],
            $this->createMock(EntityExistence::class),
            '/0/'
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->customerRepository->expects($this->once())
            ->method('search')
            ->with(static::callback(static fn (Criteria $c) => $c->getIds() !== []))
            ->willReturn(new EntitySearchResult(
                'customer',
                0,
                new CustomerCollection(),
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $this->salesChannelRepository->expects($this->never())
            ->method('search');

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository, $this->customerRepository);
        $subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateUpdateUsesSalesChannelFromExistingCustomer(): void
    {
        $ids = new IdsCollection();
        $languageId = $ids->get('lang1');
        $salesChannelId = $ids->get('sc1');

        $customer = (new CustomerEntity())->assign([
            'id' => $ids->get('customer1'),
            'salesChannelId' => $salesChannelId,
        ]);
        $customers = new CustomerCollection([$customer]);

        $language = new LanguageEntity();
        $language->setId($languageId);
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setLanguages(new LanguageCollection([$language]));
        $salesChannels = new SalesChannelCollection([$salesChannel]);

        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $command = new UpdateCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            ['language_id' => $ids->getBytes('lang1')],
            ['id' => $ids->getBytes('customer1')],
            $this->createMock(EntityExistence::class),
            '/0/'
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'customer',
                1,
                $customers,
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'sales_channel',
                1,
                $salesChannels,
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository, $this->customerRepository);
        $subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateInsertPassesWhenLanguageInSalesChannel(): void
    {
        $ids = new IdsCollection();
        $languageId = $ids->get('lang1');
        $salesChannelId = $ids->get('sc1');

        $customer = (new CustomerEntity())->assign([
            'id' => $ids->get('customer1'),
            'salesChannelId' => $salesChannelId,
        ]);
        $customers = new CustomerCollection([$customer]);

        $language = new LanguageEntity();
        $language->setId($languageId);
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setLanguages(new LanguageCollection([$language]));
        $salesChannels = new SalesChannelCollection([$salesChannel]);

        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $command = new InsertCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            [
                'language_id' => $ids->getBytes('lang1'),
                'sales_channel_id' => $ids->getBytes('sc1'),
            ],
            ['id' => $ids->getBytes('customer1')],
            $this->createMock(EntityExistence::class),
            '/0/'
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'customer',
                1,
                $customers,
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->with(static::callback(static fn (Criteria $c) => $c->hasAssociation('languages')))
            ->willReturn(new EntitySearchResult(
                'sales_channel',
                1,
                $salesChannels,
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository, $this->customerRepository);
        $subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateAddsViolationWhenLanguageNotInSalesChannel(): void
    {
        $ids = new IdsCollection();
        $salesChannelId = $ids->get('sc1');

        $customer = (new CustomerEntity())->assign([
            'id' => $ids->get('customer1'),
            'salesChannelId' => $salesChannelId,
        ]);
        $customers = new CustomerCollection([$customer]);

        $language = new LanguageEntity();
        $language->setId($ids->get('lang1'));
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setLanguages(new LanguageCollection([$language]));
        $salesChannels = new SalesChannelCollection([$salesChannel]);

        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $command = new InsertCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            [
                'language_id' => $ids->getBytes('langOther'),
                'sales_channel_id' => $ids->getBytes('sc1'),
            ],
            ['id' => $ids->getBytes('customer1')],
            $this->createMock(EntityExistence::class),
            '/0/'
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'customer',
                1,
                $customers,
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'sales_channel',
                1,
                $salesChannels,
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository, $this->customerRepository);
        $subscriber->validate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);
        static::assertSame(
            CustomerLanguageSalesChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL,
            $exceptions[0]->getViolations()->get(0)->getCode()
        );
        static::assertSame('/0/', $exceptions[0]->getPath());
        static::assertStringContainsString($ids->get('langOther'), $exceptions[0]->getViolations()->get(0)->getMessage());
    }

    public function testValidateAddsViolationWhenSalesChannelNotInCollection(): void
    {
        $ids = new IdsCollection();

        $customer = (new CustomerEntity())->assign([
            'id' => $ids->get('customer1'),
            'salesChannelId' => $ids->get('sc1'),
        ]);
        $customers = new CustomerCollection([$customer]);

        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $command = new InsertCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            [
                'language_id' => $ids->getBytes('lang1'),
                'sales_channel_id' => $ids->getBytes('scMissing'),
            ],
            ['id' => $ids->getBytes('customer1')],
            $this->createMock(EntityExistence::class),
            '/0/'
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'customer',
                1,
                $customers,
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'sales_channel',
                0,
                new SalesChannelCollection(),
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository, $this->customerRepository);
        $subscriber->validate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);
    }

    public function testValidateAddsViolationWhenSalesChannelHasNoLanguages(): void
    {
        $ids = new IdsCollection();
        $salesChannelId = $ids->get('sc1');

        $customer = (new CustomerEntity())->assign([
            'id' => $ids->get('customer1'),
            'salesChannelId' => $salesChannelId,
        ]);
        $customers = new CustomerCollection([$customer]);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setLanguages(new LanguageCollection());
        $salesChannels = new SalesChannelCollection([$salesChannel]);

        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $command = new InsertCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            [
                'language_id' => $ids->getBytes('lang1'),
                'sales_channel_id' => $ids->getBytes('sc1'),
            ],
            ['id' => $ids->getBytes('customer1')],
            $this->createMock(EntityExistence::class),
            '/0/'
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'customer',
                1,
                $customers,
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'sales_channel',
                1,
                $salesChannels,
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository, $this->customerRepository);
        $subscriber->validate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);
        static::assertSame(
            CustomerLanguageSalesChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL,
            $exceptions[0]->getViolations()->get(0)->getCode()
        );
    }

    public function testValidateLoadCustomersSalesChannelsWithEmptyResult(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $command = new InsertCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            [
                'language_id' => $ids->getBytes('lang1'),
                'sales_channel_id' => $ids->getBytes('sc1'),
            ],
            ['id' => $ids->getBytes('customerNew')],
            $this->createMock(EntityExistence::class),
            '/0/'
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->customerRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'customer',
                0,
                new CustomerCollection(),
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $language = new LanguageEntity();
        $language->setId($ids->get('lang1'));
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($ids->get('sc1'));
        $salesChannel->setLanguages(new LanguageCollection([$language]));
        $salesChannels = new SalesChannelCollection([$salesChannel]);

        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'sales_channel',
                1,
                $salesChannels,
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository, $this->customerRepository);
        $subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateInsertWithPrimaryKeyWithoutIdUsesPayloadSalesChannel(): void
    {
        $ids = new IdsCollection();
        $language = new LanguageEntity();
        $language->setId($ids->get('lang1'));
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($ids->get('sc1'));
        $salesChannel->setLanguages(new LanguageCollection([$language]));
        $salesChannels = new SalesChannelCollection([$salesChannel]);

        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $command = new InsertCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            [
                'language_id' => $ids->getBytes('lang1'),
                'sales_channel_id' => $ids->getBytes('sc1'),
            ],
            [],
            $this->createMock(EntityExistence::class),
            '/0/'
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->customerRepository->expects($this->never())
            ->method('search');

        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'sales_channel',
                1,
                $salesChannels,
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository, $this->customerRepository);
        $subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }
}
