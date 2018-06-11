<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDatasheet\Event;

use Shopware\Core\Content\Product\Aggregate\ProductDatasheet\ProductDatasheetDefinition;
use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
