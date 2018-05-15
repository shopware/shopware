<?php declare(strict_types=1);

namespace Shopware\Content\Catalog\Event\Catalog;

use Shopware\Content\Catalog\Definition\CatalogDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CatalogWrittenEvent extends WrittenEvent
{
    public const NAME = 'catalog.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CatalogDefinition::class;
    }
}
