<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Seo;

use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

trait StorefrontSalesChannelTestHelper
{
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
        $country = $this->getValidCountryId();

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

        return $this->createSalesChannelContext($salesChannel);
    }

    public function updateSalesChannelNavigationEntryPoint(string $id, string $categoryId): void
    {
        /** @var EntityRepositoryInterface $repo */
        $repo = $this->getContainer()->get('sales_channel.repository');

        $repo->update([['id' => $id, 'navigationCategoryId' => $categoryId]], Context::createDefaultContext());
    }

    private function createSalesChannelContext(SalesChannelEntity $salesChannel): SalesChannelContext
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
