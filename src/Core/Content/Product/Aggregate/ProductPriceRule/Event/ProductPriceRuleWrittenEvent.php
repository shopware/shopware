<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductPriceRule\Event;

use Shopware\Core\Content\Product\Aggregate\ProductPriceRule\ProductPriceRuleDefinition;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;

class ProductPriceRuleWrittenEvent extends WrittenEvent
{
    public const NAME = 'product_price_rule.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ProductPriceRuleDefinition::class;
    }
}
