<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog\Event;

use Shopware\Core\Content\Catalog\Struct\CatalogSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
