<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Service;

use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentAccountResource;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\SiteVerificationResource;
use Shopware\Core\Content\GoogleShopping\Exception\GoogleShoppingException;
use Shopware\Core\Content\GoogleShopping\Exception\SalesChannelIsNotLinkedToProductExport;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;

class GoogleShoppingMerchantAccount
{
    /**
     * @var EntityRepositoryInterface
     */
    private $googleMerchantAccountRepository;

    /**
     * @var GoogleShoppingContentAccountResource
     */
    private $contentAccountResource;

    /**
     * @var SiteVerificationResource
     */
    private $siteVerificationResource;

    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $googleAccountRepository;

    public function __construct(
        EntityRepositoryInterface $googleMerchantAccountRepository,
        GoogleShoppingContentAccountResource $contentAccountResource,
        SiteVerificationResource $siteVerificationResource,
        EntityRepositoryInterface $salesChannelRepository,
        EntityRepositoryInterface $googleAccountRepository
    ) {
        $this->googleMerchantAccountRepository = $googleMerchantAccountRepository;
        $this->contentAccountResource = $contentAccountResource;
        $this->siteVerificationResource = $siteVerificationResource;
        $this->salesChannelRepository = $salesChannelRepository;
        $this->googleAccountRepository = $googleAccountRepository;
    }

    public function getInfo(string $merchantId): array
    {
        return $this->contentAccountResource->get($merchantId, $merchantId);
    }

    public function getStatus(string $merchantId): array
    {
        $status = $this->contentAccountResource->getStatus($merchantId, $merchantId);
        $status['isSuspended'] = array_key_exists('accountLevelIssues', $status)
            ? $this->isAccountSuspended(json_decode(json_encode($status['accountLevelIssues']), true))
            : false;

        return $status;
    }

    public function list(): array
    {
        $accounts = $this->contentAccountResource->list();

        return array_map(function ($account) {
            return [
                'id' => $account['id'],
                'name' => $account['name'],
            ];
        }, $accounts);
    }

    public function create(string $googleMerchantAccountId, string $googleShoppingAccountId, Context $context): string
    {
        $account = [
            'id' => Uuid::randomHex(),
            'merchantId' => $googleMerchantAccountId,
            'accountId' => $googleShoppingAccountId,
        ];

        $this->googleMerchantAccountRepository->create([$account], $context);

        return $account['id'];
    }

    public function unassign(string $accountId, string $merchantId, Context $context): EntityWrittenContainerEvent
    {
        $this->googleAccountRepository->update([['id' => $accountId, 'tosAcceptedAt' => null]], $context);

        return $this->googleMerchantAccountRepository->delete([['id' => $merchantId]], $context);
    }

    public function updateWebsiteUrl(string $merchantId, string $websiteUrl): void
    {
        $this->contentAccountResource->updateWebsiteUrl($merchantId, $merchantId, $websiteUrl);
    }

    public function getSalesChannelDomain(string $salesChannelId, Context $context): SalesChannelDomainEntity
    {
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('productExports.salesChannelDomain');

        $productExport = $this->getProductExportsByCriteria($criteria, $salesChannelId, $context);

        return $productExport->getSalesChannelDomain();
    }

    public function getStorefrontSalesChannel(string $salesChannelId, Context $context): SalesChannelEntity
    {
        $criteria = new Criteria([$salesChannelId]);
        $criteria->addAssociation('productExports.storefrontSalesChannel');

        $productExport = $this->getProductExportsByCriteria($criteria, $salesChannelId, $context);

        return $productExport->getStorefrontSalesChannel();
    }

    public function isSiteVerified(string $siteUrl, string $merchantId): bool
    {
        return !empty($this->automaticallyVerifySite($siteUrl, $merchantId)['id']);
    }

    public function claimWebsiteUrl(string $merchantId, bool $force = false)
    {
        return $this->contentAccountResource->claimWebsite($merchantId, $merchantId, $force);
    }

    public function update(Request $request, string $googleMerchantAccountId)
    {
        return $this->contentAccountResource->update($request, $googleMerchantAccountId, $googleMerchantAccountId);
    }

    private function getProductExportsByCriteria(Criteria $criteria, string $salesChannelId, Context $context): ProductExportEntity
    {
        /** @var SalesChannelEntity $salesChannel */
        $salesChannel = $this->salesChannelRepository->search($criteria, $context)->get($salesChannelId);

        if (!$salesChannel->getProductExports()->first()) {
            throw new SalesChannelIsNotLinkedToProductExport();
        }

        return $salesChannel->getProductExports()->first();
    }

    private function automaticallyVerifySite(string $siteUrl, string $merchantId)
    {
        try {
            $verifyResult = $this->siteVerificationResource->get($siteUrl);
        } catch (GoogleShoppingException $exception) {
            $verifyResult = $this->siteVerificationResource->insert($siteUrl, 'ANALYTICS', true);
        }

        try {
            $this->claimWebsiteUrl($merchantId);
        } catch (GoogleShoppingException $exception) {
            // nth
        }

        return $verifyResult;
    }

    private function isAccountSuspended(array $accountLevelIssues): bool
    {
        foreach ($accountLevelIssues as $issue) {
            if (isset($issue['severity']) && $issue['severity'] === 'critical') {
                return true;
            }
        }

        return false;
    }
}
