<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\CloneTrait;
use Shopware\Core\Framework\Struct\JsonSerializableTrait;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class Source implements \JsonSerializable
{
    use CloneTrait;
    use JsonSerializableTrait;

    public function __construct(
        protected string $url,
        protected string $shopId,
        protected string $appVersion
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
}
