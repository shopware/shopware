<?php declare(strict_types=1);

namespace Shopware\Content\Product\Aggregate\ProductDatasheet\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Content\Product\Aggregate\ProductDatasheet\ProductDatasheetDefinition;

class ProductDatasheetWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_datasheet.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductDatasheetDefinition::class;
    }
}
