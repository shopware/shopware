<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\SalesChannel;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<MediaCollection>
 */
#[Package('discovery')]
class MediaRouteResponse extends StoreApiResponse
{
    public function getMediaCollection(): MediaCollection
    {
        return $this->object;
    }
}
