<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App;

use Shopware\Core\Framework\App\ShopId\ShopId;
use Shopware\Core\Framework\App\Url\AppUrlVerifier;
use Shopware\Core\Framework\App\Url\VerificationState;
use Shopware\Core\Framework\App\Url\VerificationStatus;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class PassingAppUrlVerifier extends AppUrlVerifier
{
    public function __construct()
    {
    }

    public function forceVerify(ShopId $shopId): VerificationState
    {
        return new VerificationState(VerificationStatus::PASS, 1, new \DateTimeImmutable());
    }
}
