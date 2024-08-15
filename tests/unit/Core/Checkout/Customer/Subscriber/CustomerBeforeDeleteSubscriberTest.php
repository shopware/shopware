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
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Serializer\StructNormalizer;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[Package('services-settings')]
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

        $salesChannelContextService = static::createMock(SalesChannelContextService::class);
        $salesChannelContextService->method('get')->willReturn(Generator::createSalesChannelContext());

        $eventDispatcher = new EventDispatcher();

        $structNormalizer = new StructNormalizer();

        $jsonEntityEncoder = new JsonEntityEncoder(new Serializer([$structNormalizer], []));

        $subscriber = new CustomerBeforeDeleteSubscriber(
            $customerRepository,
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
            function (CustomerDeletedEvent $event) use (&$customerDeletedEventCount, $customer, $serializedCustomer): void {
                ++$customerDeletedEventCount;
                static::assertSame($customer, $event->getCustomer());

                if (Feature::isActive('v6.7.0.0')) {
                    static::assertSame([
                        'customer' => $serializedCustomer,
                    ], $event->getValues());

                    return;
                }

                static::assertSame([
                    'customer' => $serializedCustomer,
                    'customerId' => $customer->getId(),
                    'customerNumber' => $customer->getCustomerNumber(),
                    'customerEmail' => $customer->getEmail(),
                    'customerFirstName' => $customer->getFirstName(),
                    'customerLastName' => $customer->getLastName(),
                    'customerCompany' => $customer->getCompany(),
                    'customerSalutationId' => $customer->getSalutationId(),
                ], $event->getValues());
            }
        );

        $eventDispatcher->dispatch($entityDeleteEvent);
        $entityDeleteEvent->success();

        static::assertSame(1, $customerDeletedEventCount);
    }
}
