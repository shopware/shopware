<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpKernel\KernelInterface;

trait SalesChannelApiTestBehaviour
{
    use BasicTestDataBehaviour;

    /**
     * @var array<string>
     */
    protected array $salesChannelIds = [];

    /**
     * @var KernelBrowser|null
     */
    private $salesChannelApiBrowser;

    /**
     * @after
     */
    public function resetSalesChannelApiTestCaseTrait(): void
    {
        if (!$this->salesChannelApiBrowser) {
            return;
        }

        $connection = $this->salesChannelApiBrowser
            ->getContainer()
            ->get(Connection::class);

        try {
            $connection->executeStatement(
                'DELETE FROM sales_channel WHERE id IN (:salesChannelIds)',
                ['salesChannelIds' => $this->salesChannelIds],
                ['salesChannelIds' => ArrayParameterType::STRING]
            );
        } catch (\Exception $ex) {
            // nth
        }

        $this->salesChannelIds = [];
        $this->salesChannelApiBrowser = null;
    }

    public function getSalesChannelApiSalesChannelId(): string
    {
        if (!$this->salesChannelIds) {
            throw new \LogicException('The sales channel id can only be requested after calling `createSalesChannelApiClient`.');
        }

        return end($this->salesChannelIds);
    }

    /**
     * @param array<mixed> $salesChannelOverride
     */
    public function createCustomSalesChannelBrowser(array $salesChannelOverride = []): KernelBrowser
    {
        $kernel = $this->getKernel();
        $salesChannelApiBrowser = KernelLifecycleManager::createBrowser($kernel);
        $salesChannelApiBrowser->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32),
        ]);

        $this->authorizeSalesChannelBrowser($salesChannelApiBrowser, $salesChannelOverride);

        return $salesChannelApiBrowser;
    }

    /**
     * @param array<mixed> $salesChannelOverride
     * @param array<mixed> $options
     */
    public function createSalesChannelContext(array $salesChannelOverride = [], array $options = []): SalesChannelContext
    {
        $salesChannel = $this->createSalesChannel($salesChannelOverride);

        return $this->createContext($salesChannel, $options);
    }

    public function login(?KernelBrowser $browser = null): string
    {
        $email = Uuid::randomHex() . '@example.com';
        $customerId = $this->createCustomer('shopware', $email);

        if (!$browser) {
            $browser = $this->getSalesChannelBrowser();
        }

        $browser
            ->request(
                'POST',
                '/store-api/account/login',
                [
                    'email' => $email,
                    'password' => 'shopware',
                ]
            );

        $response = $browser->getResponse();

        // After login successfully, the context token will be set in the header
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        if (empty($contextToken)) {
            throw new \RuntimeException('Cannot login with the given credential account');
        }

        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        return $customerId;
    }

    abstract protected static function getKernel(): KernelInterface;

    protected function getSalesChannelBrowser(): KernelBrowser
    {
        if ($this->salesChannelApiBrowser) {
            return $this->salesChannelApiBrowser;
        }

        return $this->salesChannelApiBrowser = $this->createSalesChannelBrowser();
    }

    /**
     * @param array<mixed> $salesChannelOverrides
     */
    protected function createSalesChannelBrowser(
        ?KernelInterface $kernel = null,
        bool $enableReboot = false,
        array $salesChannelOverrides = []
    ): KernelBrowser {
        if (!$kernel) {
            $kernel = $this->getKernel();
        }

        $salesChannelApiBrowser = KernelLifecycleManager::createBrowser($kernel, $enableReboot);
        $salesChannelApiBrowser->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_' . PlatformRequest::HEADER_CONTEXT_TOKEN => Random::getAlphanumericString(32),
        ]);

        $this->authorizeSalesChannelBrowser($salesChannelApiBrowser, $salesChannelOverrides);

        return $salesChannelApiBrowser;
    }

    private function createCustomer(?string $password = null, ?string $email = null, ?bool $guest = false): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        if ($email === null) {
            $email = Uuid::randomHex() . '@example.com';
        }

        if ($password === null) {
            $password = Uuid::randomHex();
        }

        $this->getContainer()->get('customer.repository')->create([
            [
                'id' => $customerId,
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'defaultShippingAddress' => [
                    'id' => $addressId,
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Musterstraße 1',
                    'city' => 'Schöppingen',
                    'zipcode' => '12345',
                    'salutationId' => $this->getValidSalutationId(),
                    'countryId' => $this->getValidCountryId(),
                ],
                'defaultBillingAddressId' => $addressId,
                'defaultPaymentMethod' => [
                    'name' => 'Invoice',
                    'active' => true,
                    'description' => 'Default payment method',
                    'handlerIdentifier' => SyncTestPaymentHandler::class,
                    'availabilityRule' => [
                        'id' => Uuid::randomHex(),
                        'name' => 'true',
                        'priority' => 0,
                        'conditions' => [
                            [
                                'type' => 'cartCartAmount',
                                'value' => [
                                    'operator' => '>=',
                                    'amount' => 0,
                                ],
                            ],
                        ],
                    ],
                    'salesChannels' => [
                        [
                            'id' => TestDefaults::SALES_CHANNEL,
                        ],
                    ],
                ],
                'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
                'email' => $email,
                'password' => $password,
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'guest' => $guest,
                'salutationId' => $this->getValidSalutationId(),
                'customerNumber' => '12345',
            ],
        ], Context::createDefaultContext());

        return $customerId;
    }

    /**
     * @param array<string, string> $salesChannel
     * @param array<string, mixed> $options
     */
    private function createContext(array $salesChannel, array $options): SalesChannelContext
    {
        $factory = $this->getContainer()->get(SalesChannelContextFactory::class);
        $context = $factory->create(Uuid::randomHex(), $salesChannel['id'], $options);

        $ruleLoader = $this->getContainer()->get(CartRuleLoader::class);
        $ruleLoader->loadByToken($context, $context->getToken());

        return $context;
    }

    /**
     * @param array<string, mixed> $salesChannelOverride
     */
    private function authorizeSalesChannelBrowser(KernelBrowser $salesChannelApiClient, array $salesChannelOverride = []): void
    {
        $salesChannel = $this->createSalesChannel($salesChannelOverride);

        $this->salesChannelIds[] = $salesChannel['id'];

        $header = 'HTTP_' . str_replace('-', '_', mb_strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $salesChannelApiClient->setServerParameter($header, $salesChannel['accessKey']);
        $salesChannelApiClient->setServerParameter('test-sales-channel-id', $salesChannel['id']);
    }

    /**
     * @param array<mixed> $salesChannelOverride
     *
     * @return array<mixed>
     */
    private function createSalesChannel(array $salesChannelOverride = []): array
    {
        /** @var EntityRepository $salesChannelRepository */
        $salesChannelRepository = $this->getContainer()->get('sales_channel.repository');
        $paymentMethod = $this->getAvailablePaymentMethod();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('domains.url', 'http://localhost'));
        $salesChannelIds = $salesChannelRepository->searchIds($criteria, Context::createDefaultContext());

        if (!isset($salesChannelOverride['domains']) && $salesChannelIds->firstId() !== null) {
            $salesChannelRepository->delete([['id' => $salesChannelIds->firstId()]], Context::createDefaultContext());
        }

        $salesChannel = array_merge([
            'id' => $salesChannelOverride['id'] ?? Uuid::randomHex(),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'name' => 'API Test case sales channel',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $paymentMethod->getId(),
            'paymentMethods' => [['id' => $paymentMethod->getId()]],
            'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
            'navigationCategoryId' => $this->getValidCategoryId(),
            'countryId' => $this->getValidCountryId(null),
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => $salesChannelOverride['languages'] ?? [['id' => Defaults::LANGUAGE_SYSTEM]],
            'customerGroupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost',
                ],
            ],
            'countries' => [['id' => $this->getValidCountryId(null)]],
        ], $salesChannelOverride);

        $salesChannelRepository->upsert([$salesChannel], Context::createDefaultContext());

        return $salesChannel;
    }

    private function assignSalesChannelContext(?KernelBrowser $customBrowser = null): void
    {
        $browser = $customBrowser ?: $this->getSalesChannelBrowser();
        $browser->request('GET', '/store-api/context');
        $response = $browser->getResponse();
        /** @var string $content */
        $content = $response->getContent();
        $content = json_decode($content, true);
        if (isset($content['errors'])) {
            throw new \RuntimeException($content['errors'][0]['detail']);
        }
        $browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $content['token']);
    }

    private function getRandomId(string $table): string
    {
        return (string) $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM ' . $table);
    }
}
