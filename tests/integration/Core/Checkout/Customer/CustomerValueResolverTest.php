<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Customer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerValueResolver;
use Shopware\Core\Checkout\Customer\Exception\BadCredentialsException;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Integration\Traits\CustomerTestTrait;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[Package('checkout')]
class CustomerValueResolverTest extends TestCase
{
    use CustomerTestTrait;
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<CurrencyCollection>
     */
    private EntityRepository $currencyRepository;

    private AccountService $accountService;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->currencyRepository = $this->getContainer()->get('currency.repository');

        $this->createTestSalesChannel();

        $this->accountService = $this->getContainer()->get(AccountService::class);
        /** @var AbstractSalesChannelContextFactory $salesChannelContextFactory */
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $this->salesChannelContext = $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    #[DataProvider('loginRequiredAnnotationData')]
    public function testCustomerResolver(bool $loginRequired, bool $context, bool $pass): void
    {
        $resolver = $this->getContainer()->get(CustomerValueResolver::class);

        $salesChannelResolver = $this->getContainer()->get(SalesChannelRequestContextResolver::class);

        $currencyId = $this->getCurrencyId('USD');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_DOMAIN_CURRENCY_ID, $currencyId);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['store-api']);

        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, $this->loginCustomer());

        if ($loginRequired) {
            $request->attributes->set(PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED, $loginRequired);
        }

        if ($context) {
            $salesChannelResolver->resolve($request);
        }

        $exception = null;

        try {
            $generator = $resolver->resolve($request, new ArgumentMetadata('', CustomerEntity::class, false, false, ''));
            if ($generator instanceof \Traversable) {
                iterator_to_array($generator);
            }
        } catch (\Exception $e) {
            $exception = $e;
        }

        if ($pass) {
            static::assertNull($exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
        } else {
            static::assertInstanceOf(\RuntimeException::class, $exception, 'Exception: ' . ($exception !== null ? print_r($exception->getMessage(), true) : 'No Exception'));
        }
    }

    /**
     * @return array<string, array{0: bool, 1: bool, 2: bool}>
     */
    public static function loginRequiredAnnotationData(): array
    {
        return [
            'Success Case' => [
                true, // loginRequired
                true, // context
                true, // pass
            ],
            'Missing annotation LoginRequired' => [
                false,
                true,
                false,
            ],
            'Missing sales-channel context' => [
                false,
                false,
                false,
            ],
        ];
    }

    private function loginCustomer(): string
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createCustomer($email);

        try {
            return $this->accountService->loginByCredentials($email, 'shopware', $this->salesChannelContext);
        } catch (BadCredentialsException) {
            // nth
        }

        return '';
    }

    private function getCurrencyId(string $isoCode): ?string
    {
        $currency = $this->currencyRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('isoCode', $isoCode)),
            Context::createDefaultContext()
        )->getEntities()->first();

        return $currency?->getId();
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
