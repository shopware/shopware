<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payload;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\CloneTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal only for use by the app-system
 *
 * @method array{url: string, shopId: string, appVersion: string} jsonSerialize()
 */
#[Package('core')]
class Source implements \JsonSerializable
{
    use CloneTrait;
    use JsonSerializableTrait;

    /**
     * @param list<string> $inAppPurchases
     */
    public function __construct(
        protected string $url,
        protected string $shopId,
        protected string $appVersion,
        protected array $inAppPurchases = [],
    ) {
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getShopId(): string
    {
        return $this->shopId;
    }

    public function getAppVersion(): string
    {
        return $this->appVersion;
    }

    /**
     * @return list<string>
     */
    public function getInAppPurchases(): array
    {
        return $this->inAppPurchases;
    }
}
