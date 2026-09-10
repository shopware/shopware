<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterSubscribeUrlEvent;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\NoContentResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(NewsletterSubscribeRoute::class)]
class NewsletterSubscribeRouteTest extends TestCase
{
    private Stub&SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = static::createStub(SalesChannelContext::class);
    }

    public function testSubscribeWithDOIEnabled(): void
    {
        $this->salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $requestData = new RequestDataBag();
        $requestData->add([
            'email' => 'test@example.com',
            'option' => 'direct',
            'firstName' => 'Y',
            'lastName' => 'Tran',
        ]);

        $newsletterRecipientEntity = new NewsletterRecipientEntity();
        $newsletterRecipientEntity->setId(Uuid::randomHex());
        $newsletterRecipientEntity->setConfirmedAt(new \DateTime());
        $newsletterRecipientEntity->setStatus(NewsletterSubscribeRoute::STATUS_OPT_IN);

        $entityRepository = new StaticEntityRepository([
            [$newsletterRecipientEntity->getId()],
            new NewsletterRecipientCollection([$newsletterRecipientEntity]),
        ]);

        $systemConfig = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.newsletter.doubleOptIn' => true,
            ],
        ]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls(
                static::isInstanceOf(BuildValidationEvent::class),
                static::isInstanceOf(NewsletterSubscribeUrlEvent::class),
                static::isInstanceOf(NewsletterRegisterEvent::class),
            );

        $newsletterSubscribeRoute = new NewsletterSubscribeRoute(
            $entityRepository,
            static::createStub(DataValidator::class),
            $eventDispatcher,
            $systemConfig,
            static::createStub(RateLimiter::class),
            static::createStub(RequestStack::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
        );

        $response = $newsletterSubscribeRoute->subscribeWithResponse($requestData, $this->salesChannelContext, false);

        static::assertSame(NewsletterSubscribeRoute::STATUS_OPT_IN, $response->getStatus());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with NewsletterSubscribeRoute::subscribe().
     *
     * The deprecated method has to keep answering with the old response object. Extensions written
     * before v6.7 declare NoContentResponse as their own return type, so handing them the new
     * response object is a TypeError inside their code.
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedSubscribeKeepsReturningTheOldResponse(): void
    {
        $this->salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $requestData = new RequestDataBag();
        $requestData->add([
            'email' => 'test@example.com',
            'option' => 'direct',
        ]);

        $newsletterRecipientEntity = new NewsletterRecipientEntity();
        $newsletterRecipientEntity->setId(Uuid::randomHex());
        $newsletterRecipientEntity->setStatus(NewsletterSubscribeRoute::STATUS_DIRECT);
        $newsletterRecipientEntity->setEmail('test@example.com');

        $entityRepository = new StaticEntityRepository([
            [$newsletterRecipientEntity->getId()],
            new NewsletterRecipientCollection([$newsletterRecipientEntity]),
            new NewsletterRecipientCollection([$newsletterRecipientEntity]),
        ]);

        $newsletterSubscribeRoute = new NewsletterSubscribeRoute(
            $entityRepository,
            static::createStub(DataValidator::class),
            static::createStub(EventDispatcherInterface::class),
            new StaticSystemConfigService([TestDefaults::SALES_CHANNEL => ['core.newsletter.doubleOptIn' => false]]),
            static::createStub(RateLimiter::class),
            static::createStub(RequestStack::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
        );

        $response = $newsletterSubscribeRoute->subscribe($requestData, $this->salesChannelContext, false);

        static::assertInstanceOf(NoContentResponse::class, $response);
        static::assertSame(204, $response->getStatusCode());
    }

    public function testSubscribeWithDOIDisabled(): void
    {
        $this->salesChannelContext->method('getSalesChannelId')->willReturn(TestDefaults::SALES_CHANNEL);

        $requestData = new RequestDataBag();
        $requestData->add([
            'email' => 'test@example.com',
            'option' => 'subscribe',
            'firstName' => 'Y',
            'lastName' => 'Tran',
        ]);

        $newsletterRecipientEntity = new NewsletterRecipientEntity();
        $newsletterRecipientEntity->setId(Uuid::randomHex());
        $newsletterRecipientEntity->setConfirmedAt(new \DateTime());
        $newsletterRecipientEntity->setStatus(NewsletterSubscribeRoute::STATUS_DIRECT);

        $entityRepository = new StaticEntityRepository([
            [$newsletterRecipientEntity->getId()],
            new NewsletterRecipientCollection([$newsletterRecipientEntity]),
        ]);

        $systemConfig = new StaticSystemConfigService([
            TestDefaults::SALES_CHANNEL => [
                'core.newsletter.doubleOptIn' => false,
            ],
        ]);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls(
                static::isInstanceOf(BuildValidationEvent::class),
                static::isInstanceOf(NewsletterSubscribeUrlEvent::class),
                static::isInstanceOf(NewsletterConfirmEvent::class),
            );

        $newsletterSubscribeRoute = new NewsletterSubscribeRoute(
            $entityRepository,
            static::createStub(DataValidator::class),
            $eventDispatcher,
            $systemConfig,
            static::createStub(RateLimiter::class),
            static::createStub(RequestStack::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
        );

        $response = $newsletterSubscribeRoute->subscribeWithResponse($requestData, $this->salesChannelContext, false);

        static::assertSame(NewsletterSubscribeRoute::STATUS_DIRECT, $response->getStatus());
    }

    /**
     * @param array<string, string> $data
     * @param array<string, string> $properties
     * @param array<int, mixed> $constraints
     */
    #[DataProvider('validatorDataProvider')]
    public function testSubscribeWithValidation(array $data, array $properties, array $constraints): void
    {
        $requestData = new RequestDataBag();
        $requestData->add($data);

        $newsletterRecipientEntity = new NewsletterRecipientEntity();
        $newsletterRecipientEntity->setId(Uuid::randomHex());
        $newsletterRecipientEntity->setConfirmedAt(new \DateTime());

        $entityRepository = new StaticEntityRepository([
            [$newsletterRecipientEntity->getId()],
            new NewsletterRecipientCollection([$newsletterRecipientEntity]),
        ]);

        $mock = static::createStub(DataValidator::class);
        $mock->method('validate')->willReturnCallback(static function (array $data, DataValidationDefinition $definition) use ($properties, $constraints): void {
            foreach ($properties as $propertyName => $value) {
                static::assertEquals($value, $data[$propertyName] ?? null);
                static::assertEquals($definition->getProperties()[$propertyName] ?? null, $constraints);
            }
        });

        $newsletterSubscribeRoute = new NewsletterSubscribeRoute(
            $entityRepository,
            $mock,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(RateLimiter::class),
            static::createStub(RequestStack::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
        );

        $newsletterSubscribeRoute->subscribeWithResponse($requestData, $this->salesChannelContext, false);
    }

    public static function validatorDataProvider(): \Generator
    {
        yield 'subscribe with no correct validation' => [
            [
                'email' => 'test@example.com',
                'option' => 'direct',
                'firstName' => 'Y http://localhost',
                'lastName' => 'Tran http://localhost',
            ],
            ['firstName' => 'Y http://localhost', 'lastName' => 'Tran http://localhost'],
            [
                new NotBlank(),
                new Regex(
                    pattern: NewsletterSubscribeRoute::DOMAIN_NAME_REGEX,
                    message: 'error.urlNotAllowed',
                    match: false,
                ),
            ],
        ];

        yield 'subscribe correct is validation' => [
            [
                'email' => 'test@example.com',
                'option' => 'direct',
                'firstName' => 'Y',
                'lastName' => 'Tran',
            ],
            ['firstName' => 'Y', 'lastName' => 'Tran'],
            [
                new NotBlank(),
                new Regex(
                    pattern: NewsletterSubscribeRoute::DOMAIN_NAME_REGEX,
                    message: 'error.urlNotAllowed',
                    match: false,
                ),
            ],
        ];
    }

    public function testRateLimitation(): void
    {
        $requestData = new RequestDataBag();
        $requestData->add([
            'email' => 'test@example.com',
            'option' => 'direct',
        ]);

        $newsletterRecipientEntity = new NewsletterRecipientEntity();
        $newsletterRecipientEntity->setId(Uuid::randomHex());
        $newsletterRecipientEntity->setConfirmedAt(new \DateTime());

        $entityRepository = new StaticEntityRepository([
            [$newsletterRecipientEntity->getId()],
            new NewsletterRecipientCollection([$newsletterRecipientEntity]),
        ]);

        $requestStack = new RequestStack();
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $requestStack->push($request);

        $rateLimiterMock = $this->createMock(RateLimiter::class);
        $rateLimiterMock
            ->expects($this->once())
            ->method('ensureAccepted')
            ->willReturnCallback(static function (string $route, string $key): void {
                static::assertSame($route, RateLimiter::NEWSLETTER_FORM);
                static::assertSame($key, '127.0.0.1');
            });

        $newsletterSubscribeRoute = new NewsletterSubscribeRoute(
            $entityRepository,
            static::createStub(DataValidator::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            $rateLimiterMock,
            $requestStack,
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
        );

        $newsletterSubscribeRoute->subscribeWithResponse($requestData, $this->salesChannelContext, false);
    }

    public function testRateLimitationWithThrowException(): void
    {
        $requestData = new RequestDataBag();
        $requestData->add([
            'email' => 'test@example.com',
            'option' => 'direct',
        ]);

        $newsletterRecipientEntity = new NewsletterRecipientEntity();
        $newsletterRecipientEntity->setId(Uuid::randomHex());
        $newsletterRecipientEntity->setConfirmedAt(new \DateTime());

        $requestStack = new RequestStack();
        $request = new Request();
        $request->server->set('REMOTE_ADDR', '127.0.0.1');
        $requestStack->push($request);

        $rateLimiterMock = $this->createMock(RateLimiter::class);
        $rateLimiterMock
            ->expects($this->once())
            ->method('ensureAccepted')
            ->willThrowException(new RateLimitExceededException(2));

        $newsletterSubscribeRoute = new NewsletterSubscribeRoute(
            static::createStub(EntityRepository::class),
            static::createStub(DataValidator::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(SystemConfigService::class),
            $rateLimiterMock,
            $requestStack,
            static::createStub(StoreApiCustomFieldMapper::class),
            static::createStub(EntityRepository::class),
        );

        static::expectException(RateLimitExceededException::class);

        $newsletterSubscribeRoute->subscribeWithResponse($requestData, $this->salesChannelContext, false);
    }

    /**
     * @param array{'core.newsletter.doubleOptIn': bool, 'core.newsletter.doubleOptInRegistered': bool} $doiSettings
     * @param array{id: string, email: string} $customerData
     * @param array{id: string, email: string} $recipientData
     */
    #[DataProvider('subscribeToNewsletterViaCmsFormDataProvider')]
    public function testSubscribeToNewsletterViaCmsForm(
        array $doiSettings,
        bool $isLoggedIn,
        bool $isRegistered,
        array $customerData,
        array $recipientData,
        string $expectedEvent
    ): void {
        $requestData = new RequestDataBag();
        $requestData->add([
            'email' => $customerData['email'],
            'option' => 'subscribe',
            'storefrontUrl' => 'https://shopware.com',
        ]);

        $systemConfig = new StaticSystemConfigService($doiSettings);

        if ($isLoggedIn) {
            $this->salesChannelContext->method('getCustomerId')->willReturn($customerData['id']);
        } else {
            $this->salesChannelContext->method('getCustomerId')->willReturn(null);
        }

        $customerRepository = static::createStub(EntityRepository::class);

        $customerEntity = new CustomerEntity();
        $customerEntity->setId($customerData['id']);
        $customerEntity->setEmail($customerData['email']);

        /* depending on whether the customer is already registered the search result returns a customer or not */
        $customerSearchResult = new EntitySearchResult(
            'customer',
            $isRegistered ? 1 : 0,
            new EntityCollection([$customerEntity]),
            null,
            $isRegistered ? new Criteria([$customerEntity->getId()]) : new Criteria(),
            $this->salesChannelContext->getContext(),
        );

        $customerRepository->method('search')->willReturn($customerSearchResult);

        $newsletterRecipientEntity = new NewsletterRecipientEntity();
        $newsletterRecipientEntity->setId($recipientData['id']);
        $newsletterRecipientEntity->setEmail($recipientData['email']);

        $newsletterRecipientSearchResult = new EntitySearchResult(
            'newsletter_recipient',
            1,
            new EntityCollection([$newsletterRecipientEntity]),
            null,
            new Criteria([$newsletterRecipientEntity->getEmail()]),
            $this->salesChannelContext->getContext(),
        );

        $newsletterRecipientRepository = static::createStub(EntityRepository::class);
        $newsletterRecipientRepository->method('search')->willReturn($newsletterRecipientSearchResult);

        $eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $dispatchedEvents = [];
        $eventDispatcher
            ->method('dispatch')
            ->willReturnCallback(static function ($event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event;

                return $event;
            });

        $newsletterSubscribeRoute = new NewsletterSubscribeRoute(
            $newsletterRecipientRepository,
            static::createStub(DataValidator::class),
            $eventDispatcher,
            $systemConfig,
            static::createStub(RateLimiter::class),
            static::createStub(RequestStack::class),
            static::createStub(StoreApiCustomFieldMapper::class),
            $customerRepository,
        );

        $newsletterSubscribeRoute->subscribeWithResponse($requestData, $this->salesChannelContext, false);

        static::assertInstanceOf(BuildValidationEvent::class, $dispatchedEvents[0]);

        /* different events are dispatched depending on whether DOI is required or not */
        if ($expectedEvent === NewsletterConfirmEvent::class) {
            static::assertCount(2, $dispatchedEvents);
            static::assertArrayHasKey(1, $dispatchedEvents);
            static::assertInstanceOf(NewsletterConfirmEvent::class, $dispatchedEvents[1]);
        }

        if ($expectedEvent === NewsletterRegisterEvent::class) {
            static::assertCount(3, $dispatchedEvents);
            static::assertArrayHasKey(1, $dispatchedEvents);
            static::assertArrayHasKey(2, $dispatchedEvents);
            static::assertInstanceOf(NewsletterSubscribeUrlEvent::class, $dispatchedEvents[1]);
            static::assertInstanceOf(NewsletterRegisterEvent::class, $dispatchedEvents[2]);
        }
    }

    public static function subscribeToNewsletterViaCmsFormDataProvider(): \Generator
    {
        yield 'logged-in customer subscribes with different email address expects DOI; settings: doi is true, for registered customers false' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => true,
                'core.newsletter.doubleOptInRegistered' => false,
            ],
            'isLoggedIn' => true,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'test@example.com',
            ],
            'expectedEvent' => NewsletterRegisterEvent::class,
        ];

        yield 'logged-in customer subscribes with different email address expects DOI; settings: doi is true, for registered customers true' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => true,
                'core.newsletter.doubleOptInRegistered' => true,
            ],
            'isLoggedIn' => true,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'test@example.com',
            ],
            'expectedEvent' => NewsletterRegisterEvent::class,
        ];

        // this is not a logical combination, but it is currently possible
        yield 'logged-in customer subscribes with different email expects DOI; settings: doi is false, for registered customers true' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => false,
                'core.newsletter.doubleOptInRegistered' => true,
            ],
            'isLoggedIn' => true,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'test@example.com',
            ],
            'expectedEvent' => NewsletterRegisterEvent::class,
        ];

        yield 'logged-in customer subscribes with different email address expects no DOI; settings: doi is false, for registered customers false' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => false,
                'core.newsletter.doubleOptInRegistered' => false,
            ],
            'isLoggedIn' => true,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'test@example.com',
            ],
            'expectedEvent' => NewsletterConfirmEvent::class,
        ];

        yield 'logged-in customer subscribes with own email expects no DOI; settings: doi is true, for registered customers false' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => true,
                'core.newsletter.doubleOptInRegistered' => false,
            ],
            'isLoggedIn' => true,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'expectedEvent' => NewsletterConfirmEvent::class,
        ];

        yield 'logged-in customer subscribes with own email expects DOI; settings: doi is true, for registered customers true' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => true,
                'core.newsletter.doubleOptInRegistered' => true,
            ],
            'isLoggedIn' => true,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'customer@example.com',
            ],
            'expectedEvent' => NewsletterRegisterEvent::class,
        ];

        yield 'logged-in customer subscribes with own email expects no DOI; settings: doi is false, for registered customers false' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => false,
                'core.newsletter.doubleOptInRegistered' => false,
            ],
            'isLoggedIn' => true,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'customer@example.com',
            ],
            'expectedEvent' => NewsletterConfirmEvent::class,
        ];

        // this is not a logical combination, but it is currently possible
        yield 'logged-in customer subscribes with own email expects DOI; settings: doi is false, for registered customers true' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => false,
                'core.newsletter.doubleOptInRegistered' => true,
            ],
            'isLoggedIn' => true,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'customer@example.com',
            ],
            'expectedEvent' => NewsletterRegisterEvent::class,
        ];

        yield 'not logged-in but registered customer subscribes with own email expects no DOI; settings: doi is false, for registered customers false' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => false,
                'core.newsletter.doubleOptInRegistered' => false,
            ],
            'isLoggedIn' => false,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'customer@example.com',
            ],
            'expectedEvent' => NewsletterConfirmEvent::class,
        ];

        yield 'not logged-in but registered customer subscribes with different email expects no DOI; settings: doi is false, for registered customers false' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => false,
                'core.newsletter.doubleOptInRegistered' => false,
            ],
            'isLoggedIn' => false,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'recipient@example.com',
            ],
            'expectedEvent' => NewsletterConfirmEvent::class,
        ];

        yield 'not logged-in but registered customer subscribes with own email expects no DOI; settings: doi is false, for registered customers true' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => false,
                'core.newsletter.doubleOptInRegistered' => true,
            ],
            'isLoggedIn' => false,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'customer@example.com',
            ],
            'expectedEvent' => NewsletterConfirmEvent::class,
        ];

        yield 'not logged-in but registered customer subscribes with different email expects no DOI; settings: doi is false, for registered customers true' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => false,
                'core.newsletter.doubleOptInRegistered' => true,
            ],
            'isLoggedIn' => false,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'recipient@example.com',
            ],
            'expectedEvent' => NewsletterConfirmEvent::class,
        ];

        yield 'not logged-in but registered customer subscribes with own email expects DOI; settings: doi is true, for registered customers false' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => true,
                'core.newsletter.doubleOptInRegistered' => false,
            ],
            'isLoggedIn' => false,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'customer@example.com',
            ],
            'expectedEvent' => NewsletterRegisterEvent::class,
        ];

        yield 'not logged-in but registered customer subscribes with different email expects DOI; settings: doi is true, for registered customers false' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => true,
                'core.newsletter.doubleOptInRegistered' => false,
            ],
            'isLoggedIn' => false,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'recipient@example.com',
            ],
            'expectedEvent' => NewsletterRegisterEvent::class,
        ];

        yield 'not logged-in but registered customer subscribes with own email expects DOI; settings: doi is true, for registered customers true' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => true,
                'core.newsletter.doubleOptInRegistered' => true,
            ],
            'isLoggedIn' => false,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'customer@example.com',
            ],
            'expectedEvent' => NewsletterRegisterEvent::class,
        ];

        yield 'not logged-in but registered customer subscribes different own email expects DOI; settings: doi is true, for registered customers true' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => true,
                'core.newsletter.doubleOptInRegistered' => true,
            ],
            'isLoggedIn' => false,
            'isRegistered' => true,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'recipient@example.com',
            ],
            'expectedEvent' => NewsletterRegisterEvent::class,
        ];

        yield 'not registered customer subscribes and expects no DOI; settings: doi is false, for registered customers false' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => false,
                'core.newsletter.doubleOptInRegistered' => false,
            ],
            'isLoggedIn' => false,
            'isRegistered' => false,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'customer@example.com',
            ],
            'expectedEvent' => NewsletterConfirmEvent::class,
        ];

        yield 'not registered customer subscribes and expects no DOI; settings: doi is false, for registered customers true' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => false,
                'core.newsletter.doubleOptInRegistered' => true,
            ],
            'isLoggedIn' => false,
            'isRegistered' => false,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'customer@example.com',
            ],
            'expectedEvent' => NewsletterConfirmEvent::class,
        ];

        yield 'not registered customer subscribes and expects DOI; settings: doi is true, for registered customers true' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => true,
                'core.newsletter.doubleOptInRegistered' => true,
            ],
            'isLoggedIn' => false,
            'isRegistered' => false,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'customer@example.com',
            ],
            'expectedEvent' => NewsletterRegisterEvent::class,
        ];

        yield 'not registered customer subscribes and expects DOI; settings: doi is true, for registered customers false' => [
            'doiSettings' => [
                'core.newsletter.doubleOptIn' => true,
                'core.newsletter.doubleOptInRegistered' => false,
            ],
            'isLoggedIn' => false,
            'isRegistered' => false,
            'customerData' => [
                'id' => 'customer-id',
                'email' => 'customer@example.com',
            ],
            'recipientData' => [
                'id' => 'recipient-id',
                'email' => 'customer@example.com',
            ],
            'expectedEvent' => NewsletterRegisterEvent::class,
        ];
    }
}
