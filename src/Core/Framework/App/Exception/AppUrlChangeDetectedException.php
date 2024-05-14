<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class AppUrlChangeDetectedException extends \Exception
{
    public function __construct(
        private readonly string $previousUrl,
        private readonly string $currentUrl,
        private readonly string $shopId,
    ) {
        parent::__construct(sprintf('Detected APP_URL change, was "%s" and is now "%s".', $previousUrl, $currentUrl));
    }

    public function getPreviousUrl(): string
    {
        return $this->previousUrl;
    }

    public function getCurrentUrl(): string
    {
        return $this->currentUrl;
    }

    public function getShopId(): string
    {
        return $this->shopId;
    }
}
