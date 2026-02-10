<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [CustomerDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
        $this->salesChannelRepository = $this->createMock(EntityRepository::class);
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

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository);
        $subscriber->validate($event);
    }

    public function testValidateSkipsWhenNoCommands(): void
    {
        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $event = new PreWriteValidationEvent($writeContext, []);

        $this->salesChannelRepository->expects($this->never())->method('search');

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository);
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

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository);
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

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository);
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

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository);
        $subscriber->validate($event);
    }

    public function testValidateSkipsWhenInsertHasNoSalesChannelId(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $command = new InsertCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            ['language_id' => $ids->getBytes('lang1')],
            ['id' => $ids->getBytes('customer1')],
            $this->createMock(EntityExistence::class),
            '/0/'
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->salesChannelRepository->expects($this->never())->method('search');

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository);
        $subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateSkipsWhenUpdateHasNoSalesChannelIdAndCustomerNotInAnyChannel(): void
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

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository);
        $subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateUpdateResolvesSalesChannelFromLoadedSalesChannelsCustomers(): void
    {
        $ids = new IdsCollection();
        $languageId = $ids->get('lang1');
        $salesChannelId = $ids->get('sc1');

        $customer = (new CustomerEntity())->assign([
            'id' => $ids->get('customer1'),
            'salesChannelId' => $salesChannelId,
        ]);
        $language = new LanguageEntity();
        $language->setId($languageId);
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setLanguages(new LanguageCollection([$language]));
        $salesChannel->assign(['customers' => new CustomerCollection([$customer])]);
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

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository);
        $subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateInsertPassesWhenLanguageInSalesChannel(): void
    {
        $ids = new IdsCollection();
        $languageId = $ids->get('lang1');
        $salesChannelId = $ids->get('sc1');

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

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository);
        $subscriber->validate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testValidateAddsViolationWhenLanguageNotInSalesChannel(): void
    {
        $ids = new IdsCollection();
        $salesChannelId = $ids->get('sc1');

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

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository);
        $subscriber->validate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);
        $this->assertLanguageNotInSalesChannelViolation($exceptions[0], $ids->get('langOther'));
    }

    public function testValidateAddsViolationOnUpdateWhenLanguageNotInResolvedSalesChannel(): void
    {
        $ids = new IdsCollection();
        $salesChannelId = $ids->get('sc1');

        $customer = (new CustomerEntity())->assign([
            'id' => $ids->get('customer1'),
            'salesChannelId' => $salesChannelId,
        ]);
        $language = new LanguageEntity();
        $language->setId($ids->get('lang1'));
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setLanguages(new LanguageCollection([$language]));
        $salesChannel->assign(['customers' => new CustomerCollection([$customer])]);
        $salesChannels = new SalesChannelCollection([$salesChannel]);

        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $command = new UpdateCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            ['language_id' => $ids->getBytes('langOther')],
            ['id' => $ids->getBytes('customer1')],
            $this->createMock(EntityExistence::class),
            '/0/'
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

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

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository);
        $subscriber->validate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);
        $this->assertLanguageNotInSalesChannelViolation($exceptions[0], $ids->get('langOther'));
    }

    /**
     * @return iterable<string, array{SalesChannelCollection, Context, string}>
     */
    public static function provideLanguageNotInSalesChannelCases(): iterable
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();

        yield 'sales channel not in collection' => [
            new SalesChannelCollection(),
            $context,
            $ids->getBytes('scMissing'),
        ];

        $salesChannelEmptyLangs = new SalesChannelEntity();
        $salesChannelEmptyLangs->setId($ids->get('sc1'));
        $salesChannelEmptyLangs->setLanguages(new LanguageCollection());
        yield 'sales channel has no languages' => [
            new SalesChannelCollection([$salesChannelEmptyLangs]),
            $context,
            $ids->getBytes('sc1'),
        ];

        $salesChannelNullLangs = new SalesChannelEntity();
        $salesChannelNullLangs->setId($ids->get('sc1'));
        yield 'sales channel languages is null' => [
            new SalesChannelCollection([$salesChannelNullLangs]),
            $context,
            $ids->getBytes('sc1'),
        ];
    }

    #[DataProvider('provideLanguageNotInSalesChannelCases')]
    public function testValidateAddsViolationWhenLanguageNotAvailableInSalesChannel(
        SalesChannelCollection $salesChannels,
        Context $context,
        string $salesChannelIdBytes
    ): void {
        $ids = new IdsCollection();
        $writeContext = WriteContext::createFromContext($context);
        $command = new InsertCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            [
                'language_id' => $ids->getBytes('lang1'),
                'sales_channel_id' => $salesChannelIdBytes,
            ],
            ['id' => $ids->getBytes('customer1')],
            $this->createMock(EntityExistence::class),
            '/0/'
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                'sales_channel',
                $salesChannels->count(),
                $salesChannels,
                new AggregationResultCollection(),
                new Criteria(),
                $context
            ));

        $subscriber = new CustomerLanguageSalesChannelSubscriber($this->salesChannelRepository);
        $subscriber->validate($event);

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);
        $this->assertLanguageNotInSalesChannelViolation($exceptions[0], $ids->get('lang1'));
    }

    private function assertLanguageNotInSalesChannelViolation(WriteConstraintViolationException $e, string $languageId): void
    {
        static::assertSame(CustomerLanguageSalesChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL, $e->getViolations()->get(0)->getCode());
        static::assertSame('/languageId', $e->getViolations()->get(0)->getPropertyPath());
        static::assertStringContainsString($languageId, (string) $e->getViolations()->get(0)->getMessage());
    }
}
