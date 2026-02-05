<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerBeforeDeleteSubscriber;
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

        $criteria = (new Criteria([$customerId]))
            ->addAssociations([
                'salutation',
                'defaultBillingAddress.country',
                'defaultBillingAddress.countryState',
                'defaultBillingAddress.salutation',
                'defaultShippingAddress.country',
                'defaultShippingAddress.countryState',
                'defaultShippingAddress.salutation',
            ]);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                CustomerEntity::class,
                1,
                new CustomerCollection([$customer]),
                null,
                $criteria,
                Context::createDefaultContext()
            ),
        ], $customerDefinition);

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection([])]);

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
            $criteria,
            $customerDefinition,
            $customer,
            '/api/customer'
        );

        $eventDispatcher->addListener(
            CustomerDeletedEvent::class,
            function (CustomerDeletedEvent $event) use (&$customerDeletedEventCount, $customer, $serializedCustomer): void {
                ++$customerDeletedEventCount;
                static::assertSame($customer, $event->getCustomer());

                static::assertSame([
                    'customer' => $serializedCustomer,
                ], $event->getValues());
            }
        );

        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $customerDeletedEventCount);
    }

    public function testBeforeDeleteDoesNotDispatchEventWhenNoCustomerIds(): void
    {
        $definitionInstanceRegistry = static::createMock(DefinitionInstanceRegistry::class);
        $customerDefinition = new CustomerDefinition();
        $customerDefinition->compile($definitionInstanceRegistry);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository([], $customerDefinition);

        /** @var StaticEntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection([])]);

        $salesChannelContextService = static::createMock(SalesChannelContextService::class);
        $salesChannelContextService->expects($this->never())->method('get');

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
            []
        );

        $customerDeletedEventCount = 0;
        $eventDispatcher->addListener(
            CustomerDeletedEvent::class,
            function () use (&$customerDeletedEventCount): void {
                ++$customerDeletedEventCount;
            }
        );

        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(0, $customerDeletedEventCount);
    }

    public function testResolveEffectiveLanguageIdsWithSalesChannelLanguages(): void
    {
        $salesChannelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $customerId = Uuid::randomBytes();

        $customer = (new CustomerEntity())
            ->assign([
                'id' => Uuid::fromBytesToHex($customerId),
                'salesChannelId' => $salesChannelId,
                'languageId' => $languageId,
                'customerNumber' => 'SW1001',
                'email' => 'baz@bar.com',
                'firstName' => 'baz',
                'lastName' => 'qux',
            ]);

        $definitionInstanceRegistry = static::createMock(DefinitionInstanceRegistry::class);
        $customerDefinition = new CustomerDefinition();
        $customerDefinition->compile($definitionInstanceRegistry);

        $criteria = (new Criteria([$customerId]))
            ->addAssociations([
                'salutation',
                'defaultBillingAddress.country',
                'defaultBillingAddress.countryState',
                'defaultBillingAddress.salutation',
                'defaultShippingAddress.country',
                'defaultShippingAddress.countryState',
                'defaultShippingAddress.salutation',
            ]);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                CustomerEntity::class,
                1,
                new CustomerCollection([$customer]),
                null,
                $criteria,
                Context::createDefaultContext()
            ),
        ], $customerDefinition);

        $languageEntity = (new LanguageEntity())->assign(['id' => $languageId]);
        $salesChannel = (new SalesChannelEntity())->assign([
            'id' => $salesChannelId,
        ]);
        $salesChannel->setLanguages(new LanguageCollection([$languageEntity]));

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
        $eventDispatcher->addListener(
            CustomerDeletedEvent::class,
            function () use (&$customerDeletedEventCount): void {
                ++$customerDeletedEventCount;
            }
        );

        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $customerDeletedEventCount);
    }

    public function testResolveEffectiveLanguageIdsSkipsSalesChannelWithNoLanguages(): void
    {
        $salesChannelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $customerId = Uuid::randomBytes();

        $customer = (new CustomerEntity())
            ->assign([
                'id' => Uuid::fromBytesToHex($customerId),
                'salesChannelId' => $salesChannelId,
                'languageId' => $languageId,
                'customerNumber' => 'SW1002',
                'email' => 'qux@bar.com',
                'firstName' => 'qux',
                'lastName' => 'quux',
            ]);

        $definitionInstanceRegistry = static::createMock(DefinitionInstanceRegistry::class);
        $customerDefinition = new CustomerDefinition();
        $customerDefinition->compile($definitionInstanceRegistry);

        $criteria = (new Criteria([$customerId]))
            ->addAssociations([
                'salutation',
                'defaultBillingAddress.country',
                'defaultBillingAddress.countryState',
                'defaultBillingAddress.salutation',
                'defaultShippingAddress.country',
                'defaultShippingAddress.countryState',
                'defaultShippingAddress.salutation',
            ]);

        /** @var StaticEntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = new StaticEntityRepository([
            new EntitySearchResult(
                CustomerEntity::class,
                1,
                new CustomerCollection([$customer]),
                null,
                $criteria,
                Context::createDefaultContext()
            ),
        ], $customerDefinition);

        $salesChannel = (new SalesChannelEntity())->assign(['id' => $salesChannelId]);
        $salesChannel->setLanguages(new LanguageCollection([]));

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
        $eventDispatcher->addListener(
            CustomerDeletedEvent::class,
            function () use (&$customerDeletedEventCount): void {
                ++$customerDeletedEventCount;
            }
        );

        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $customerDeletedEventCount);
    }
}
