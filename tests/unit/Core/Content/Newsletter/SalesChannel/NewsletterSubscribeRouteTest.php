<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Newsletter\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientCollection;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\Event\NewsletterConfirmEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Newsletter\Event\NewsletterSubscribeUrlEvent;
use Shopware\Core\Content\Newsletter\NewsletterException;
use Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
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
#[Package('buyers-experience')]
#[CoversClass(NewsletterSubscribeRoute::class)]
class NewsletterSubscribeRouteTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);
    }

    public function testSubscribeWithDOIEnabled(): void
    {
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
            ->expects(static::once())
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls(
                static::isInstanceOf(BuildValidationEvent::class),
                static::isInstanceOf(NewsletterSubscribeUrlEvent::class),
                static::isInstanceOf(NewsletterRegisterEvent::class),
            );

        $newsletterSubscribeRoute = new NewsletterSubscribeRoute(
            $entityRepository,
            $this->createMock(DataValidator::class),
            $eventDispatcher,
            $systemConfig,
            $this->createMock(RateLimiter::class),
            $this->createMock(RequestStack::class),
        );

        $newsletterSubscribeRoute->subscribe($requestData, $this->salesChannelContext, false);
    }

    public function testSubscribeWithDOIDisabled(): void
    {
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
            ->expects(static::once())
            ->method('dispatch')
            ->willReturnOnConsecutiveCalls(
                static::isInstanceOf(BuildValidationEvent::class),
                static::isInstanceOf(NewsletterSubscribeUrlEvent::class),
                static::isInstanceOf(NewsletterConfirmEvent::class),
            );

        $newsletterSubscribeRoute = new NewsletterSubscribeRoute(
            $entityRepository,
            $this->createMock(DataValidator::class),
            $eventDispatcher,
            $systemConfig,
            $this->createMock(RateLimiter::class),
            $this->createMock(RequestStack::class),
        );

        $newsletterSubscribeRoute->subscribe($requestData, $this->salesChannelContext, false);
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

        $mock = $this->createMock(DataValidator::class);
        $mock->method('validate')->willReturnCallback(function (array $data, DataValidationDefinition $definition) use ($properties, $constraints): void {
            foreach ($properties as $propertyName => $value) {
                static::assertEquals($value, $data[$propertyName] ?? null);
                static::assertEquals($definition->getProperties()[$propertyName] ?? null, $constraints);
            }
        });

        $newsletterSubscribeRoute = new NewsletterSubscribeRoute(
            $entityRepository,
            $mock,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SystemConfigService::class),
            $this->createMock(RateLimiter::class),
            $this->createMock(RequestStack::class),
        );

        $newsletterSubscribeRoute->subscribe($requestData, $this->salesChannelContext, false);
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
                new Regex([
                    'pattern' => NewsletterSubscribeRoute::DOMAIN_NAME_REGEX,
                    'match' => false,
                ]),
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
                new Regex([
                    'pattern' => NewsletterSubscribeRoute::DOMAIN_NAME_REGEX,
                    'match' => false,
                ]),
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
            ->expects(static::once())
            ->method('ensureAccepted')
            ->willReturnCallback(function (string $route, string $key): void {
                static::assertSame($route, RateLimiter::NEWSLETTER_FORM);
                static::assertSame($key, '127.0.0.1');
            });

        $newsletterSubscribeRoute = new NewsletterSubscribeRoute(
            $entityRepository,
            $this->createMock(DataValidator::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SystemConfigService::class),
            $rateLimiterMock,
            $requestStack,
        );

        $newsletterSubscribeRoute->subscribe($requestData, $this->salesChannelContext, false);
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
            ->expects(static::once())
            ->method('ensureAccepted')
            ->willThrowException(new RateLimitExceededException(2));

        $newsletterSubscribeRoute = new NewsletterSubscribeRoute(
            $this->createMock(EntityRepository::class),
            $this->createMock(DataValidator::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SystemConfigService::class),
            $rateLimiterMock,
            $requestStack,
        );

        static::expectException(NewsletterException::class);

        $newsletterSubscribeRoute->subscribe($requestData, $this->salesChannelContext, false);
    }
}
