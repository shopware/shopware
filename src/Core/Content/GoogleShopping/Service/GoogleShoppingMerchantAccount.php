<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Service;

use Shopware\Core\Content\GoogleShopping\Client\Adapter\GoogleShoppingContentAccountResource;
use Shopware\Core\Content\GoogleShopping\Client\Adapter\SiteVerificationResource;
use Shopware\Core\Content\GoogleShopping\Exception\GoogleShoppingException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Uuid\Uuid;
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

    public function __construct(
        EntityRepositoryInterface $googleMerchantAccountRepository,
        GoogleShoppingContentAccountResource $contentAccountResource,
        SiteVerificationResource $siteVerificationResource
    ) {
        $this->googleMerchantAccountRepository = $googleMerchantAccountRepository;
        $this->contentAccountResource = $contentAccountResource;
        $this->siteVerificationResource = $siteVerificationResource;
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

    public function delete(string $id, Context $context): EntityWrittenContainerEvent
    {
        return $this->googleMerchantAccountRepository->delete([['id' => $id]], $context);
    }

    public function updateWebsiteUrl(string $merchantId, string $websiteUrl): void
    {
        $this->contentAccountResource->updateWebsiteUrl($merchantId, $merchantId, $websiteUrl);
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
