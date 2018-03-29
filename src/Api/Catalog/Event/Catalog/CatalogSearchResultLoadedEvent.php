<?php declare(strict_types=1);

namespace Shopware\Api\Catalog\Event\Catalog;

use Shopware\Api\Catalog\Struct\CatalogSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CatalogSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'catalog.search.result.loaded';

    /**
     * @var CatalogSearchResult
     */
    protected $result;

    public function __construct(CatalogSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
