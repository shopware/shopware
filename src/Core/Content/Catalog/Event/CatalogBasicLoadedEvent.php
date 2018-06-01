<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Catalog\Collection\CatalogBasicCollection;
use Shopware\Core\Framework\Event\NestedEvent;

class CatalogBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'catalog.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var CatalogBasicCollection
     */
    protected $catalogs;

    public function __construct(CatalogBasicCollection $catalogs, Context $context)
    {
        $this->context = $context;
        $this->catalogs = $catalogs;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCatalogs(): CatalogBasicCollection
    {
        return $this->catalogs;
    }
}
