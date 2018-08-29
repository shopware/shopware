<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
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
    public function resetStorefrontApiTestCaseTrait()
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

    protected function getStorefrontClient(): Client
    {
        if ($this->storeFrontClient) {
            return $this->storeFrontClient;
        }

        return $this->storeFrontClient = $this->createStorefrontClient();
    }

    protected function createStorefrontClient(
        KernelInterface $kernel = null,
        bool $enableReboot = false
    ): Client {
        if (!$kernel) {
            $kernel = KernelLifecycleManager::getKernel();
        }

        $storefrontApiClient = KernelLifecycleManager::createClient($kernel, $enableReboot);
        $storefrontApiClient->setServerParameters([
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'HTTP_Accept' => 'application/json',
            'HTTP_X_SW_CONTEXT_TOKEN' => Uuid::uuid4()->getHex(),
            'HTTP_X_SW_TENANT_ID' => Defaults::TENANT_ID,
        ]);
        $this->authorizeStorefrontClient($storefrontApiClient);

        return $storefrontApiClient;
    }

    protected function authorizeStorefrontClient(Client $storefrontApiClient): void
    {
        $salesChannelId = Uuid::uuid4();
        $accessKey = AccessKeyHelper::generateAccessKey('sales-channel');

        /** @var RepositoryInterface $salesChannelRepository */
        $salesChannelRepository = $storefrontApiClient
            ->getContainer()
            ->get('sales_channel.repository');

        $salesChannelRepository->upsert([[
            'id' => $salesChannelId->getHex(),
            'typeId' => Defaults::SALES_CHANNEL_STOREFRONT_API,
            'name' => 'API Test case sales channel',
            'accessKey' => $accessKey,
            'languageId' => Defaults::LANGUAGE,
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => Defaults::PAYMENT_METHOD_DEBIT,
            'shippingMethodId' => Defaults::SHIPPING_METHOD,
            'countryId' => Defaults::COUNTRY,
            'catalogs' => [['id' => Defaults::CATALOG]],
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE]],
        ]], Context::createDefaultContext(Defaults::TENANT_ID));

        $this->salesChannelIds[] = $salesChannelId->getBytes();

        $header = 'HTTP_' . str_replace('-', '_', strtoupper(PlatformRequest::HEADER_ACCESS_KEY));

        $storefrontApiClient->setServerParameter($header, $accessKey);
    }
}
