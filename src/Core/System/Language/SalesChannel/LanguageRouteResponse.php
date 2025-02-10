<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<LanguageCollection>>
 */
#[Package('fundamentals@discovery')]
class LanguageRouteResponse extends StoreApiResponse
{
    public function getLanguages(): LanguageCollection
    {
        return $this->object->getEntities();
    }
}
