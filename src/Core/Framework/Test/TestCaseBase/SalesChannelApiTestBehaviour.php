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

trait SalesChannelApiTestBehaviour
{
    /**
     * @var array
     */
    protected $salesChannelIds = [];

    /**
     * @var Client|null
     */
    private $salesChannelApiClient;

    /**
     * @after
     */
    public function resetSalesChannelApiTestCaseTrait(): void
    {
        if (!$this->salesChannelApiClient) {
            return;
        }

        /** @var Connection $connection */
        $connection = $this->salesChannelApiClient
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
        $this->salesChannelApiClient = null;
    }

    public function getSalesChannelApiSalesChannelId(): string
    {
        if (!$this->salesChannelIds) {
            throw new \LogicException('The sales channel id con only be requested after calling `createSalesChannelApiClient`.');
        }

        return end($this->salesChannelIds);
    }

    public function createCustomSalesChannelClient(array $salesChannelOverride = []): Client
    {
        $kernel = KernelLifecycleManager::getKernel();
        $salesChannelApiClient = KernelLifecycleManager::createClient($kernel);
        $salesChannelApiClient->setServerParameters([
            'HTTP_Accept' => 'application/json',
        ]);
        $this->authorizeSalesChannelClient($salesChannelApiClient, $salesChannelOverride);

        return $salesChannelApiClient;
    }

    protected function getSalesChannelClient(): Client
    {
        if ($this->salesChannelApiClient) {
            return $this->salesChannelApiClient;
        }

        return $this->salesChannelApiClient = $this->createSalesChannelClient();
    }

    protected function createSalesChannelClient(
        ?KernelInterface $kernel = null,
        bool $enableReboot = false
    ): Client {
        if (!$kernel) {
            $kernel = KernelLifecycleManager::getKernel();
        }

        $salesChannelApiClient = KernelLifecycleManager::createClient($kernel, $enableReboot);
        $salesChannelApiClient->setServerParameters([
            'HTTP_Accept' => 'application/json',
        ]);
        $this->authorizeSalesChannelClient($salesChannelApiClient);

        return $salesChannelApiClient;
    }

    private function authorizeSalesChannelClient(Client $salesChannelApiClient, array $salesChannelOverride = []): void
    {
        $accessKey = AccessKeyHelper::generateAccessKey('sales-channel');

        /** @var EntityRepositoryInterface $salesChannelRepository */
        $salesChannelRepository = $salesChannelApiClient
            ->getContainer()
            ->get('sales_channel.repository');

        $salesChannel = array_merge([
            'id' => Uuid::randomHex(),
            'typeId' => Defaults::SALES_CHANNEL_TYPE_API,
            'name' => 'API Test case sales channel',
            'accessKey' => $accessKey,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'snippetSetId' => $this->getSnippetSetIdForLocale('en_GB'),
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $this->getValidPaymentMethodId(),
            'shippingMethodId' => $this->getAvailableShippingMethodId(),
            'countryId' => $this->getValidCountryId(),
            'currencies' => [['id' => Defaults::CURRENCY]],
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM]],
            'customerGroupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
        ], $salesChannelOverride);

        $salesChannelRepository->upsert([$salesChannel], Context::createDefaultContext());

        $this->salesChannelIds[] = $salesChannel['id'];

        $header = 'HTTP_' . str_replace('-', '_', strtoupper(PlatformRequest::HEADER_ACCESS_KEY));
        $salesChannelApiClient->setServerParameter($header, $accessKey);
    }
}
