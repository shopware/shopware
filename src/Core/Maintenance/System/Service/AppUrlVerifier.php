<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\System\Service;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\Url\AppUrlVerifier as CoreAppUrlVerifier;
use Shopware\Core\Framework\App\Url\VerificationStatus;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class AppUrlVerifier
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly CoreAppUrlVerifier $appUrlVerifier,
        private readonly string $appEnv,
        private readonly bool $appUrlCheckDisabled
    ) {
    }

    public function isAppUrlReachable(): bool
    {
        $status = $this->appUrlVerifier->getCurrentState();

        if ($status) {
            return $status->is(VerificationStatus::PASS);
        }

        if ($this->appEnv !== 'prod' || $this->appUrlCheckDisabled) {
            // dev and test system are often not reachable and this is totally fine
            // problems occur if a prod system can't be reached
            // the check can be disabled manually e.g. for cloud
            return true;
        }

        return $this->appUrlVerifier->forceVerify(
            $this->shopIdProvider->getShopId()
        );
    }

    public function hasAppsThatNeedAppUrl(): bool
    {
        $foundApp = $this->connection->fetchOne('SELECT 1 FROM app WHERE app_secret IS NOT NULL');

        return $foundApp === '1';
    }
}
