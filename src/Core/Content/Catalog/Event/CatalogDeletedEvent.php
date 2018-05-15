<?php declare(strict_types=1);

namespace Shopware\Content\Catalog\Event;

use Shopware\Content\Catalog\CatalogDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

class CatalogDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'catalog.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return CatalogDefinition::class;
    }
}
