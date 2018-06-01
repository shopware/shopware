<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog\Event;

use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;

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
