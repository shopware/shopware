<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerBeforeDeleteSubscriber;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CustomerBeforeDeleteSubscriber::class)]
class CustomerBeforeDeleteSubscriberTest extends TestCase
{
    public function testEventsDispatched(): void
    {
        $customerId = Uuid::randomBytes();
        $customer = (new CustomerEntity())
            ->assign([
                'id' => Uuid::fromBytesToHex($customerId),
                'salesChannelId' => Uuid::randomHex(),
                'languageId' => Uuid::randomHex(),
                'customerNumber' => 'SW1000',
                'email' => 'foo@bar.com',
                'firstName' => 'foo',
                'lastName' => 'bar',
            ]);

        $definitionInstanceRegistry = static::createMock(DefinitionInstanceRegistry::class);

        $customerDefinition = new CustomerDefinition();
        $customerDefinition->compile($definitionInstanceRegistry);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                CustomerEntity::class,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria([$customerId]),
                Context::createDefaultContext()
            ),
        ], $customerDefinition);

        $salesChannelId = $customer->getSalesChannelId();
        $languageId = $customer->getLanguageId();
        $language = (new LanguageEntity())->assign(['id' => $languageId]);
        $salesChannel = (new SalesChannelEntity())->assign([
            'id' => $salesChannelId,
            'languages' => new LanguageCollection([$language]),
        ]);
        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([
            new SalesChannelCollection([$salesChannel]),
        ]);

        $salesChannelContextService = static::createMock(SalesChannelContextService::class);
        $salesChannelContextService->method('get')->willReturn(Generator::generateSalesChannelContext());

        $eventDispatcher = new EventDispatcher();

        $structNormalizer = new StructNormalizer();

        $jsonEntityEncoder = new JsonEntityEncoder(new Serializer([$structNormalizer], []));

        $subscriber = new CustomerBeforeDeleteSubscriber(
            $customerRepository,
            $salesChannelRepository,
            $salesChannelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $entityDeleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $customerDefinition,
                    ['id' => $customerId],
                    new EntityExistence(
                        'customer',
                        ['id' => $customerId],
                        true,
                        false,
                        false,
                        [
                            'exists' => true,
                            'id' => $customerId,
                        ]
                    )
                ),
            ]
        );

        $customerDeletedEventCount = 0;

        $serializedCustomer = $jsonEntityEncoder->encode(
            new Criteria(),
            $customerDefinition,
            $customer,
            '/api/customer'
        );

        $eventDispatcher->addListener(
            CustomerDeletedEvent::class,
            static function (CustomerDeletedEvent $event) use (&$customerDeletedEventCount, $customer, $serializedCustomer): void {
                ++$customerDeletedEventCount;
                static::assertSame($customer, $event->getCustomer());
                $values = $event->getValues();
                static::assertArrayHasKey('customer', $values);
                static::assertSame($serializedCustomer, $values['customer']);
            }
        );

        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $customerDeletedEventCount);
    }

    public function testBeforeDeleteWithEmptyCustomerIdsDoesNotDispatch(): void
    {
        $definitionInstanceRegistry = static::createMock(DefinitionInstanceRegistry::class);
        $customerDefinition = new CustomerDefinition();
        $customerDefinition->compile($definitionInstanceRegistry);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository([], $customerDefinition);
        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([]);
        $salesChannelContextService = static::createMock(SalesChannelContextService::class);
        $eventDispatcher = new EventDispatcher();
        $jsonEntityEncoder = static::createMock(JsonEntityEncoder::class);

        $subscriber = new CustomerBeforeDeleteSubscriber(
            $customerRepository,
            $salesChannelRepository,
            $salesChannelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $caughtEvents = 0;
        $eventDispatcher->addListener(
            CustomerDeletedEvent::class,
            static function () use (&$caughtEvents): void {
                ++$caughtEvents;
            }
        );

        $entityDeleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            []
        );
        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(0, $caughtEvents);
    }

    public function testBeforeDeleteWithSalesChannelApiSourceUsesSourceSalesChannelId(): void
    {
        $customerId = Uuid::randomBytes();
        $salesChannelIdFromSource = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $customer = (new CustomerEntity())
            ->assign([
                'id' => Uuid::fromBytesToHex($customerId),
                'salesChannelId' => Uuid::randomHex(),
                'languageId' => $languageId,
                'customerNumber' => 'SW1001',
                'email' => 'bar@baz.com',
                'firstName' => 'bar',
                'lastName' => 'baz',
            ]);

        $definitionInstanceRegistry = static::createMock(DefinitionInstanceRegistry::class);
        $customerDefinition = new CustomerDefinition();
        $customerDefinition->compile($definitionInstanceRegistry);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                CustomerEntity::class,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria([$customerId]),
                Context::createDefaultContext()
            ),
        ], $customerDefinition);

        $language = (new LanguageEntity())->assign(['id' => $languageId]);
        $salesChannel = (new SalesChannelEntity())->assign([
            'id' => $salesChannelIdFromSource,
            'languages' => new LanguageCollection([$language]),
        ]);
        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([
            new SalesChannelCollection([$salesChannel]),
        ]);

        $salesChannelContextService = static::createMock(SalesChannelContextService::class);
        $salesChannelContextService->method('get')->willReturn(Generator::generateSalesChannelContext());
        $eventDispatcher = new EventDispatcher();
        $jsonEntityEncoder = new JsonEntityEncoder(new Serializer([new StructNormalizer()], []));

        $subscriber = new CustomerBeforeDeleteSubscriber(
            $customerRepository,
            $salesChannelRepository,
            $salesChannelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $dispatchedCount = 0;
        $eventDispatcher->addListener(
            CustomerDeletedEvent::class,
            static function () use (&$dispatchedCount): void {
                ++$dispatchedCount;
            }
        );

        $context = Context::createDefaultContext(new SalesChannelApiSource($salesChannelIdFromSource));
        $entityDeleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext($context),
            [
                new DeleteCommand(
                    $customerDefinition,
                    ['id' => $customerId],
                    new EntityExistence(
                        'customer',
                        ['id' => $customerId],
                        true,
                        false,
                        false,
                        ['exists' => true, 'id' => $customerId]
                    )
                ),
            ]
        );
        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $dispatchedCount);
    }

    public function testBeforeDeleteWhenSalesChannelDoesNotHaveCustomerLanguageUsesNullLanguageId(): void
    {
        $customerId = Uuid::randomBytes();
        $salesChannelId = Uuid::randomHex();
        $customerLanguageId = Uuid::randomHex();
        $otherLanguageId = Uuid::randomHex();
        $customer = (new CustomerEntity())
            ->assign([
                'id' => Uuid::fromBytesToHex($customerId),
                'salesChannelId' => $salesChannelId,
                'languageId' => $customerLanguageId,
                'customerNumber' => 'SW1002',
                'email' => 'nolang@test.com',
                'firstName' => 'No',
                'lastName' => 'Lang',
            ]);

        $definitionInstanceRegistry = static::createMock(DefinitionInstanceRegistry::class);
        $customerDefinition = new CustomerDefinition();
        $customerDefinition->compile($definitionInstanceRegistry);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                CustomerEntity::class,
                1,
                new CustomerCollection([$customer]),
                null,
                new Criteria([$customerId]),
                Context::createDefaultContext()
            ),
        ], $customerDefinition);

        $salesChannelHasOnlyOtherLanguage = (new LanguageEntity())->assign(['id' => $otherLanguageId]);
        $salesChannel = (new SalesChannelEntity())->assign([
            'id' => $salesChannelId,
            'languages' => new LanguageCollection([$salesChannelHasOnlyOtherLanguage]),
        ]);
        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([
            new SalesChannelCollection([$salesChannel]),
        ]);

        $salesChannelContextService = static::createMock(SalesChannelContextService::class);
        $salesChannelContextService->method('get')->willReturn(Generator::generateSalesChannelContext());
        $eventDispatcher = new EventDispatcher();
        $jsonEntityEncoder = new JsonEntityEncoder(new Serializer([new StructNormalizer()], []));

        $subscriber = new CustomerBeforeDeleteSubscriber(
            $customerRepository,
            $salesChannelRepository,
            $salesChannelContextService,
            $eventDispatcher,
            $jsonEntityEncoder
        );
        $eventDispatcher->addSubscriber($subscriber);

        $dispatchedCount = 0;
        $eventDispatcher->addListener(
            CustomerDeletedEvent::class,
            static function () use (&$dispatchedCount): void {
                ++$dispatchedCount;
            }
        );

        $entityDeleteEvent = EntityDeleteEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                new DeleteCommand(
                    $customerDefinition,
                    ['id' => $customerId],
                    new EntityExistence(
                        'customer',
                        ['id' => $customerId],
                        true,
                        false,
                        false,
                        ['exists' => true, 'id' => $customerId]
                    )
                ),
            ]
        );
        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $dispatchedCount);
    }
}
