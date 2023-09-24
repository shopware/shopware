<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\StoreApiResponse;

#[Package('buyers-experience')]
class LanguageRouteResponse extends StoreApiResponse
{
    /**
     * @var EntitySearchResult<LanguageCollection>
     */
    protected $object;

    /**
     * @param EntitySearchResult<LanguageCollection> $languages
     */
    public function __construct(EntitySearchResult $languages)
    {
        parent::__construct($languages);
    }

    public function getLanguages(): LanguageCollection
    {
        return $this->object->getEntities();
    }
}
