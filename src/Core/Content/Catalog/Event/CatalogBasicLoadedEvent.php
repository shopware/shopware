<?php declare(strict_types=1);

namespace Shopware\Content\Catalog\Event;

use Shopware\Framework\Context;
use Shopware\Content\Catalog\Collection\CatalogBasicCollection;
use Shopware\Framework\Event\NestedEvent;

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
