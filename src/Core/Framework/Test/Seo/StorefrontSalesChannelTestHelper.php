<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Seo;

use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\DependencyInjection\Container;

trait StorefrontSalesChannelTestHelper
{
    public function getBrowserWithLoggedInCustomer(bool $disableCsrf = true): KernelBrowser
    {
        $browser = KernelLifecycleManager::createBrowser(KernelLifecycleManager::getKernel(), false, $disableCsrf);
        $browser->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
        ]);

        /** @var Container $container */
        $container = $this->getContainer();

        /** @var EntityRepositoryInterface $salesChannelRepository */
        $salesChannelRepository = $container->get('sales_channel.repository');
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $salesChannelRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT)),
            Context::createDefaultContext()
        )->first();

        $header = 'HTTP_' . str_replace('-', '_', mb_strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $browser->setServerParameter($header, $salesChannel->getAccessKey());
        $browser->setServerParameter('test-sales-channel-id', $salesChannel->getId());

        $customerId = Uuid::randomHex();
        $this->createCustomerWithEmail($customerId, 'foo@foo.de', 'bar', $salesChannel);
        $browser->request(
            'POST',
            $_SERVER['APP_URL'] . '/account/login',
            [
                'username' => 'foo',
                'password' => 'bar',
            ]
        );

        static::assertSame(200, $browser->getResponse()->getStatusCode());

        return $browser;
    }

    public function createStorefrontSalesChannelContext(
        string $id,
        string $name,
        string $defaultLanguageId = Defaults::LANGUAGE_SYSTEM,
        array $languageIds = [],
        ?string $categoryEntrypoint = null
    ): SalesChannelContext {
        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('sales_channel.repository');
        $languageIds[] = $defaultLanguageId;
        $languageIds = array_unique($languageIds);

        $domains = [];
        $languages = [];

        $paymentMethod = $this->getValidPaymentMethodId();
        $shippingMethod = $this->getValidShippingMethodId();
        $country = $this->getValidCountryId(null);

        foreach ($languageIds as $langId) {
            $languages[] = ['id' => $langId];
            $domains[] = [
                'languageId' => $langId,
                'currencyId' => Defaults::CURRENCY,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://example.com/' . $name . '/' . $langId,
            ];
        }

        $repo->upsert([[
            'id' => $id,
            'name' => $name,
            'typeId' => Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            'accessKey' => Uuid::randomHex(),
            'secretAccessKey' => 'foobar',
            'languageId' => $defaultLanguageId,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $paymentMethod,
            'shippingMethodId' => $shippingMethod,
            'countryId' => $country,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => $languages,
            'paymentMethods' => [['id' => $paymentMethod]],
            'shippingMethods' => [['id' => $shippingMethod]],
            'countries' => [['id' => $country]],
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'domains' => $domains,
            'navigationCategoryId' => !$categoryEntrypoint ? $this->getValidCategoryId() : $categoryEntrypoint,
        ]], Context::createDefaultContext());

        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $repo->search(new Criteria([$id]), Context::createDefaultContext())->first();

        return $this->createNewContext($salesChannel);
    }

    public function updateSalesChannelNavigationEntryPoint(string $id, string $categoryId): void
    {
        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('sales_channel.repository');

        $repo->update([['id' => $id, 'navigationCategoryId' => $categoryId]], Context::createDefaultContext());
    }

    private function createCustomerWithEmail(string $customerId, string $email, string $password, SalesChannelEntity $salesChannel): CustomerEntity
    {
        /** @var Container $container */
        $container = $this->getContainer();

        $defaultBillingAddress = Uuid::randomHex();
        /** @var EntityRepositoryInterface $customerRepository */
        $customerRepository = $container->get('customer.repository');
        $customerRepository->upsert(
            [
                [
                    'id' => $customerId,
                    'name' => 'test',
                    'email' => $email,
                    'password' => $password,
                    'firstName' => 'foo',
                    'lastName' => 'bar',
                    'groupId' => $salesChannel->getCustomerGroupId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'defaultPaymentMethodId' => $salesChannel->getPaymentMethodId(),
                    'salesChannelId' => $salesChannel->getId(),
                    'defaultBillingAddress' => [
                        'id' => $defaultBillingAddress,
                        'countryId' => $salesChannel->getCountryId(),
                        'salutationId' => $this->getValidSalutationId(),
                        'firstName' => 'foo',
                        'lastName' => 'bar',
                        'zipcode' => '48599',
                        'city' => 'gronau',
                        'street' => 'Schillerstr.',
                    ],
                    'defaultShippingAddressId' => $defaultBillingAddress,
                    'customerNumber' => 'asdf',
                ],
            ],
            Context::createDefaultContext()
        );

        return $customerRepository->search(new Criteria([$customerId]), Context::createDefaultContext())->first();
    }

    private function createNewContext(SalesChannelEntity $salesChannel): SalesChannelContext
    {
        $factory = $this->getContainer()->get(SalesChannelContextFactory::class);

        $context = $factory->create(Uuid::randomHex(), $salesChannel->getId(), []);

        $ruleLoader = $this->getContainer()->get(CartRuleLoader::class);
        $rulesProperty = ReflectionHelper::getProperty(CartRuleLoader::class, 'rules');
        $rulesProperty->setValue($ruleLoader, null);
        $ruleLoader->loadByToken($context, $context->getToken());

        return $context;
    }
}
