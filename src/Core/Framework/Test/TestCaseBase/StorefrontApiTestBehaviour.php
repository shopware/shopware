<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Component\HttpKernel\KernelInterface;

trait StorefrontApiTestBehaviour
{
    /**
     * @var array
     */
    protected $salesChannelIds = [];

    /**
     * @var Client|null
     */
    private $storeFrontClient;

    /**
     * @after
     */
    public function resetStorefrontApiTestCaseTrait(): void
    {
        if (!$this->storeFrontClient) {
            return;
        }

        /** @var Connection $connection */
        $connection = $this->storeFrontClient
            ->getContainer()
            ->get(Connection::class);

        try {
            $connection->executeQuery(
                'DELETE FROM sales_channel WHERE id IN (:salesChannelIds)',
                ['salesChannelIds' => $this->salesChannelIds],
                ['salesChannelIds' => Connection::PARAM_STR_ARRAY]
            );
        } catch (\Exception $ex) {
            // nth
        }

        $this->salesChannelIds = [];
        $this->storeFrontClient = null;
    }

    public function getStorefrontApiSalesChannelId(): string
    {
        if (!$this->salesChannelIds) {
            throw new \LogicException('The sales channel id con only be requested after calling `createStorefrontClient`.');
        }

        return end($this->salesChannelIds);
    }

    public function createCustomStorefrontClient(array $salesChannelOverride = []): Client
    {
        $kernel = KernelLifecycleManager::getKernel();
        $storefrontApiClient = KernelLifecycleManager::createClient($kernel);
        $storefrontApiClient->setServerParameters([
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'HTTP_Accept' => 'application/json',
            'HTTP_X_SW_CONTEXT_TOKEN' => Uuid::randomHex(),
        ]);
        $this->authorizeStorefrontClient($storefrontApiClient, $salesChannelOverride);

        return $storefrontApiClient;
    }

    protected function getStorefrontClient(): Client
    {
        if ($this->storeFrontClient) {
            return $this->storeFrontClient;
        }

        return $this->storeFrontClient = $this->createStorefrontClient();
    }

    protected function createStorefrontClient(
        ?KernelInterface $kernel = null,
        bool $enableReboot = false
    ): Client {
        if (!$kernel) {
            $kernel = KernelLifecycleManager::getKernel();
        }

        $storefrontApiClient = KernelLifecycleManager::createClient($kernel, $enableReboot);
        $storefrontApiClient->setServerParameters([
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'HTTP_Accept' => 'application/json',
            'HTTP_X_SW_CONTEXT_TOKEN' => Uuid::randomHex(),
        ]);
        $this->authorizeStorefrontClient($storefrontApiClient);

        return $storefrontApiClient;
    }

    private function authorizeStorefrontClient(Client $storefrontApiClient, array $salesChannelOverride = []): void
    {
        $accessKey = AccessKeyHelper::generateAccessKey('sales-channel');

        /** @var EntityRepositoryInterface $salesChannelRepository */
        $salesChannelRepository = $storefrontApiClient
            ->getContainer()
            ->get('sales_channel.repository');

        $salesChannel = array_merge([
            'id' => Uuid::randomHex(),
            'typeId' => Defaults::SALES_CHANNEL_STOREFRONT_API,
            'name' => 'API Test case sales channel',
            'accessKey' => $accessKey,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => Defaults::SNIPPET_BASE_SET_EN,
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'shippingMethodId' => $this->getValidShippingMethodId(),
            'countryId' => Defaults::COUNTRY,
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
        ], $salesChannelOverride);

        $salesChannelRepository->upsert([$salesChannel], Context::createDefaultContext());

        $this->salesChannelIds[] = $salesChannel['id'];

        $header = 'HTTP_' . str_replace('-', '_', strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $storefrontApiClient->setServerParameter($header, $accessKey);
    }
}
