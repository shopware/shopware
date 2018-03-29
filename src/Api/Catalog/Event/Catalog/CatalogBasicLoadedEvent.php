<?php declare(strict_types=1);

namespace Shopware\Api\Catalog\Event\Catalog;

use Shopware\Api\Catalog\Collection\CatalogBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CatalogBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'catalog.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CatalogBasicCollection
     */
    protected $catalogs;

    public function __construct(CatalogBasicCollection $catalogs, ApplicationContext $context)
    {
        $this->context = $context;
        $this->catalogs = $catalogs;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCatalogs(): CatalogBasicCollection
    {
        return $this->catalogs;
    }
}
