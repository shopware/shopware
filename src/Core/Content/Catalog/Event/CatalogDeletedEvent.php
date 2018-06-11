<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog\Event;

use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
