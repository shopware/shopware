<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerLanguageSalesChannelSubscriber;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
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

    public function testValidateSkipsCustomerUpdateCommand(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $command = new UpdateCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            ['language_id' => $ids->getBytes('lang1'), 'sales_channel_id' => $ids->getBytes('sc1')],
            ['id' => $ids->getBytes('customer1')],
            $this->createMock(EntityExistence::class),
            '/0/'
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->salesChannelRepository->expects($this->once())
            ->method('search')
            ->with(static::callback(static fn (Criteria $c) => $c->getAssociation('languages') !== null))
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

    public function testValidateSkipsWhenLanguageIdOrSalesChannelIdEmpty(): void
    {
        $ids = new IdsCollection();
        $context = Context::createDefaultContext();
        $writeContext = WriteContext::createFromContext($context);
        $command = new InsertCommand(
            $this->definitionRegistry->get(CustomerDefinition::class),
            ['language_id' => '', 'sales_channel_id' => $ids->getBytes('sc1')],
            ['id' => $ids->getBytes('customer1')],
            $this->createMock(EntityExistence::class),
            '/0/'
        );
        $event = new PreWriteValidationEvent($writeContext, [$command]);

        $this->salesChannelRepository->expects($this->once())->method('search')
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

    public function testValidateConvertsSalesChannelIdFromBytesToHex(): void
    {
        $ids = new IdsCollection();
        $languageId = $ids->get('lang1');
        $salesChannelId = $ids->get('sc1');

        $language = new LanguageEntity();
        $language->setId($languageId);
        $languages = new LanguageCollection([$language]);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setLanguages($languages);
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

    public function testValidatePassesWhenLanguageIsInSalesChannel(): void
    {
        $ids = new IdsCollection();
        $languageId = $ids->get('lang1');
        $salesChannelId = $ids->get('sc1');

        $language = new LanguageEntity();
        $language->setId($languageId);
        $languages = new LanguageCollection([$language]);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setLanguages($languages);
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
        $languages = new LanguageCollection([$language]);

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId($salesChannelId);
        $salesChannel->setLanguages($languages);
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
        static::assertSame(
            CustomerLanguageSalesChannelSubscriber::VIOLATION_LANGUAGE_NOT_IN_SALES_CHANNEL,
            $exceptions[0]->getViolations()->get(0)->getCode()
        );
    }

    public function testValidateAddsViolationWhenSalesChannelNotInCollection(): void
    {
        $ids = new IdsCollection();
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

        $exceptions = $event->getExceptions()->getExceptions();
        static::assertCount(1, $exceptions);
        static::assertInstanceOf(WriteConstraintViolationException::class, $exceptions[0]);
    }
}
