<?php declare(strict_types=1);

namespace Shopware\Api\Product\Event\ProductDatasheet;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Product\Definition\ProductDatasheetDefinition;

class ProductDatasheetDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'product_datasheet.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductDatasheetDefinition::class;
    }
}
