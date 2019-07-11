<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpKernel\KernelInterface;

trait SalesChannelApiTestBehaviour
{
    /**
     * @var array
     */
    protected $salesChannelIds = [];

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

        /** @var Connection $connection */
        $connection = $this->salesChannelApiBrowser
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
        $this->salesChannelApiBrowser = null;
    }

    public function getSalesChannelApiSalesChannelId(): string
    {
        if (!$this->salesChannelIds) {
            throw new \LogicException('The sales channel id con only be requested after calling `createSalesChannelApiClient`.');
        }

        return end($this->salesChannelIds);
    }

    public function createCustomSalesChannelBrowser(array $salesChannelOverride = []): KernelBrowser
    {
        $kernel = $this->getKernel();
        $salesChannelApiBrowser = KernelLifecycleManager::createBrowser($kernel);
        $salesChannelApiBrowser->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->authorizeSalesChannelBrowser($salesChannelApiBrowser, $salesChannelOverride);

        return $salesChannelApiBrowser;
    }

    abstract protected function getKernel(): KernelInterface;

    protected function getSalesChannelBrowser(): KernelBrowser
    {
        if ($this->salesChannelApiBrowser) {
            return $this->salesChannelApiBrowser;
        }

        return $this->salesChannelApiBrowser = $this->createSalesChannelBrowser();
    }

    protected function createSalesChannelBrowser(
        ?KernelInterface $kernel = null,
        bool $enableReboot = false
    ): KernelBrowser {
        if (!$kernel) {
            $kernel = $this->getKernel();
        }

        $salesChannelApiBrowser = KernelLifecycleManager::createBrowser($kernel, $enableReboot);
        $salesChannelApiBrowser->setServerParameters([
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->authorizeSalesChannelBrowser($salesChannelApiBrowser);

        return $salesChannelApiBrowser;
    }

    private function authorizeSalesChannelBrowser(KernelBrowser $salesChannelApiClient, array $salesChannelOverride = []): void
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
            'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => $this->getAvailablePaymentMethod()->getId(),
            'shippingMethodId' => $this->getAvailableShippingMethod()->getId(),
            'navigationCategoryId' => $this->getValidCategoryId(),
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
