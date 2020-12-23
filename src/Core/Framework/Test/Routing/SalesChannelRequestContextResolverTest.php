<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Test\Customer\SalesChannel\CustomerTestTrait;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Event\SalesChannelContextResolvedEvent;
use Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

class SalesChannelRequestContextResolverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use CustomerTestTrait;

    /**
     * @var TestDataCollection
     */
    private $ids;

    /**
     * @var EntityRepositoryInterface
     */
    private $repository;

    /**
     * @var SalesChannelContextServiceInterface
     */
    private $contextService;

    /**
     * @var \Symfony\Bundle\FrameworkBundle\KernelBrowser
     */
    private $browser;

    /**
     * @var EntityRepositoryInterface
     */
    private $customerRepository;

    /**
     * @var string
     */
    private $contextToken;

    /**
     * @var AccountService
     */
    private $accountService;

    /**
     * @var SalesChannelContext
     */
    private $salesChannelContext;

    public function setUp(): void
    {
        $this->ids = new TestDataCollection();
        $this->repository = $this->getContainer()->get('currency.repository');
        $this->contextService = $this->getContainer()->get(SalesChannelContextService::class);

        $this->createTestSalesChannel();

        $this->customerRepository = $this->getContainer()->get('customer.repository');

        $this->accountService = $this->getContainer()->get(AccountService::class);
        /** @var SalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
    }

    public function testRequestSalesChannelCurrency(): void
    {
        $resolver = $this->getContainer()->get(SalesChannelRequestContextResolver::class);

        $phpunit = $this;
        $currencyId = $this->getCurrencyId('USD');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, $this->ids->get('sales-channel'));
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $currencyId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, new RouteScope(['scopes' => ['storefront']]));

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $eventDidRun = false;
        $listenerContextEventClosure = function (SalesChannelContextResolvedEvent $event) use (&$eventDidRun, $phpunit, $currencyId): void {
            $eventDidRun = true;
            $phpunit->assertSame($currencyId, $event->getSalesChannelContext()->getContext()->getCurrencyId());
        };

        $dispatcher->addListener(SalesChannelContextResolvedEvent::class, $listenerContextEventClosure);

        $resolver->resolve($request);

        $dispatcher->removeListener(SalesChannelContextResolvedEvent::class, $listenerContextEventClosure);

        static::assertTrue($eventDidRun, 'The "' . SalesChannelContextResolvedEvent::class . '" Event did not run');
    }

    /**
     * @dataProvider domainData
     */
    public function testContextCurrency(string $url, string $currencyCode, string $expectedCode): void
    {
        $currencyId = $this->getCurrencyId($currencyCode);
        $expectedCurrencyId = $expectedCode !== $currencyCode ? $this->getCurrencyId($expectedCode) : $currencyId;

        $context = $this->contextService->get($this->ids->get('sales-channel'), $this->ids->get('token'), Defaults::LANGUAGE_SYSTEM, $currencyId);

        static::assertSame($expectedCurrencyId, $context->getContext()->getCurrencyId());
    }

    public function domainData(): array
    {
        return [
            [
                'http://test.store/en-eur',
                'EUR',
                'EUR',
            ],
            [
                'http://test.store/en-usd',
                'USD',
                'USD',
            ],
        ];
    }

    /**
     * @dataProvider loginRequiredAnnotationData
     */
    public function testLoginRequiredAnnotation(bool $doLogin, bool $isGuest, ?LoginRequired $annotation, bool $pass): void
    {
        $resolver = $this->getContainer()->get(SalesChannelRequestContextResolver::class);

        $currencyId = $this->getCurrencyId('USD');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, Defaults::SALES_CHANNEL);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $currencyId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, new RouteScope(['scopes' => ['storefront']]));

        if ($doLogin) {
            $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $this->loginCustomer($isGuest));
        }

        if ($annotation) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED, $annotation);
        }

        $exception = null;

        try {
            $resolver->resolve($request);
        } catch (\Exception $e) {
            $exception = $e;
        }

        if ($pass) {
            static::assertNull($exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
        } else {
            static::assertInstanceOf(CustomerNotLoggedInException::class, $exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
        }
    }

    public function loginRequiredAnnotationData(): array
    {
        $loginRequiredNotAllowGuest = new LoginRequired([]);

        $loginRequiredAllowGuest = new LoginRequired(['allowGuest' => true]);

        return [
            [
                true, // login
                true, // guest
                $loginRequiredNotAllowGuest, // annotation
                false, // pass
            ],
            [
                true,
                false,
                $loginRequiredNotAllowGuest,
                true,
            ],
            [
                false,
                false,
                $loginRequiredNotAllowGuest,
                false,
            ],
            [
                false,
                true,
                $loginRequiredNotAllowGuest,
                false,
            ],
            [
                true,
                true,
                $loginRequiredAllowGuest,
                true,
            ],
            [
                true,
                false,
                $loginRequiredAllowGuest,
                true,
            ],
            [
                false,
                false,
                $loginRequiredAllowGuest,
                false,
            ],
            [
                false,
                true,
                $loginRequiredAllowGuest,
                false,
            ],
            [
                true,
                false,
                null,
                true,
            ],
            [
                false,
                false,
                null,
                true,
            ],
            [
                true,
                true,
                null,
                true,
            ],
            [
                false,
                true,
                null,
                true,
            ],
        ];
    }

    private function loginCustomer(bool $isGuest): string
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'shopware';
        $this->createCustomer($password, $email, $isGuest);

        try {
            return $this->accountService->login($email, $this->salesChannelContext, $isGuest);
        } catch (BadCredentialsException $e) {
            // nth
        }

        return '';
    }

    private function getCurrencyId(string $isoCode): ?string
    {
        $currency = $this->repository->search(
            (new Criteria())->addFilter(new EqualsFilter('isoCode', $isoCode)),
            Context::createDefaultContext()
        )->first();

        return $currency !== null ? $currency->getId() : null;
    }

    private function createTestSalesChannel(): void
    {
        $usdCurrencyId = $this->getCurrencyId('USD');

        $this->createSalesChannel([
            'id' => $this->ids->create('sales-channel'),
            'domains' => [
                [
                    'id' => $this->ids->get('eur-domain'),
                    'url' => 'http://test.store/en-eur',
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                ],
                [
                    'id' => $this->ids->get('usd-domain'),
                    'url' => 'http://test.store/en-usd',
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => $usdCurrencyId,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                ],
            ],
        ]);
    }
}
