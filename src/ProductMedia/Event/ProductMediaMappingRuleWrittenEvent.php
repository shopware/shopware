<?php declare(strict_types=1);

namespace Shopware\ProductMedia\Event;

use Shopware\Api\Write\WrittenEvent;

class ProductMediaMappingRuleWrittenEvent extends WrittenEvent
{
    const NAME = 'product_media_mapping_rule.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'product_media_mapping_rule';
    }
}
