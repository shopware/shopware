<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductDatasheet\Event;

use Shopware\Core\Content\Product\Aggregate\ProductDatasheet\ProductDatasheetDefinition;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

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
