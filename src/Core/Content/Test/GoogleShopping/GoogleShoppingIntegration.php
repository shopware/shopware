<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\GoogleShopping;

use Shopware\Core\Content\GoogleShopping\GoogleShoppingRequest;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use function Flag\next6050;

trait GoogleShoppingIntegration
{
    public function getSampleCredential(): array
    {
        return [
            'access_token' => 'ya29.a0Adw1xeW4xei7do9ByIQaiPkxjw617yU1pAvYXRn',
            'refresh_token' => '1//0gTTgzGwplfyTCgYIARAAGBASNwF-L9Ir_K8q5k3l5M0ouz4hdlQ4hoE2vrqejreIjA',
            'created' => 1585199421,
            'id_token' => 'GOOGLE.' . base64_encode(json_encode(['name' => 'John Doe', 'email' => 'john.doe@example.com'])) . '.ID_TOKEN',
            'scope' => 'https://www.googleapis.com/auth/content https://www.googleapis.com/auth/adwords',
            'expires_in' => 3599,
        ];
    }

    public function connectGoogleShoppingMerchantAccount(string $accountId, string $merchantId)
    {
        $id = Uuid::randomHex();

        $merchantRepository = $this->getContainer()->get('google_shopping_merchant_account.repository');

        $merchantRepository->create([[
            'id' => $id,
            'accountId' => $accountId,
            'merchantId' => $merchantId,
        ]], $this->context);

        return $id;
    }

    public function createGoogleShoppingAccount(string $id, ?string $salesChannelId = null): array
    {
        $googleAccountRepository = $this->getContainer()->get('google_shopping_account.repository');

        $credential = $this->getSampleCredential();

        $googleAccount = $this->initGoogleAccountData($id, $credential, $salesChannelId);

        $googleAccountRepository->create([$googleAccount], $this->context);

        return compact('id', 'credential', 'googleAccount');
    }

    /**
     * @beforeClass
     */
    public static function beforeClass(): void
    {
        if (!next6050()) {
            static::markTestSkipped('Skipping feature test "NEXT-6050"');
        }
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

        $this->getContainer()->get('sales_channel.repository')->create([$data], $this->context);

        return $id;
    }

    public function createGoogleShoppingRequest(?string $salesChannelId)
    {
        if (empty($salesChannelId)) {
            $salesChannelId = $this->createSalesChannelGoogleShopping();
        }

        $salesChannelEntity = $this->getContainer()->get('sales_channel.repository')->search(new Criteria([$salesChannelId]), $this->context)->first();

        return new GoogleShoppingRequest($this->context, $salesChannelEntity);
    }

    public function getMockGoogleClient(): void
    {
        if ($this->getContainer()->initialized('google_shopping_client')) {
            return;
        }

        $this->getContainer()->set(
            'google_shopping_client',
            new GoogleShoppingClientMock('clientId', 'clientSecret', 'redirectUrl')
        );
    }

    private function initGoogleAccountData(string $id, array $credential, ?string $salesChannelId): array
    {
        if (empty($salesChannelId)) {
            $salesChannelId = $this->createSalesChannelGoogleShopping();
        }

        return [
            'id' => $id,
            'salesChannelId' => $salesChannelId,
            'email' => 'foo@test.co',
            'name' => 'test',
            'credential' => $credential,
        ];
    }
}
