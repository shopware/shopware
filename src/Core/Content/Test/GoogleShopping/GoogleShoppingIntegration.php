<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

trait GoogleShoppingIntegration
{
    public function createGoogleShoppingAccount(string $id): array
    {
        $googleAccountRepository = $this->getContainer()->get('google_shopping_account.repository');

        $credential = [
            'access_token' => 'ya29.a0Adw1xeW4xei7do9ByIQaiPkxjw617yU1pAvYXRn',
            'refresh_token' => '1//0gTTgzGwplfyTCgYIARAAGBASNwF-L9Ir_K8q5k3l5M0ouz4hdlQ4hoE2vrqejreIjA',
            'created' => 1585199421,
            'id_token' => 'eyJhbGciOiJSUzI1NiIsImtpZCI6IjUzYzY2YWFiNTBjZmRkOTFhMTQzNTBhNjY0ODJkYjM4MDBj',
            'scope' => 'https://www.googleapis.com/auth/content https://www.googleapis.com/auth/adwords',
            'expires_in' => 3599,
        ];

        $googleAccounts = $this->initGoogleAccountData($id, $credential);

        $googleAccountRepository->create($googleAccounts, $this->context);

        return compact('id', 'credential');
    }

    public function createSalesChannelGoogleShopping(): string
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
            'navigation' => ['name' => 'test'],
            'typeId' => Defaults::SALES_CHANNEL_TYPE_GOOGLE_SHOPPING,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'currencyVersionId' => Defaults::LIVE_VERSION,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'paymentMethodVersionId' => Defaults::LIVE_VERSION,
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'shippingMethodVersionId' => Defaults::LIVE_VERSION,
            'navigationCategoryId' => $this->getValidCategoryId(),
            'navigationCategoryVersionId' => Defaults::LIVE_VERSION,
            'countryId' => $this->getValidCountryId(),
            'countryVersionId' => Defaults::LIVE_VERSION,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'shippingMethods' => [['id' => $this->getValidShippingMethodId()]],
            'paymentMethods' => [['id' => $this->getValidPaymentMethodId()]],
            'countries' => [['id' => $this->getValidCountryId()]],
            'name' => 'first sales-channel',
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
        ];

        $this->getContainer()->get('sales_channel.repository')->create([$data], Context::createDefaultContext());

        return $id;
    }

    private function initGoogleAccountData(string $id, array $credential): array
    {
        $salesChannelId = $this->createSalesChannelGoogleShopping();

        $googleAccount = [
            [
                'id' => $id,
                'salesChannelId' => $salesChannelId,
                'email' => 'foo@test.co',
                'name' => 'test',
                'credential' => $credential,
            ],
        ];

        return $googleAccount;
    }
}
