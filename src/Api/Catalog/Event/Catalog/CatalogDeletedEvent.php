<?php declare(strict_types=1);

namespace Shopware\Api\Catalog\Event\Catalog;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Catalog\Definition\CatalogDefinition;

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
