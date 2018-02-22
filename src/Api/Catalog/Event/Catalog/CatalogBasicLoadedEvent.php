<?php declare(strict_types=1);

namespace Shopware\Api\Catalog\Event\Catalog;

use Shopware\Api\Catalog\Collection\CatalogBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class CatalogBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'catalog.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var CatalogBasicCollection
     */
    protected $Catalogs;

    public function __construct(CatalogBasicCollection $Catalogs, ShopContext $context)
    {
        $this->context = $context;
        $this->Catalogs = $Catalogs;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getCatalogs(): CatalogBasicCollection
    {
        return $this->Catalogs;
    }
}
