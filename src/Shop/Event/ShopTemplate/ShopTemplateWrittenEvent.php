<?php declare(strict_types=1);

namespace Shopware\Shop\Event\ShopTemplate;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Shop\Definition\ShopTemplateDefinition;

class ShopTemplateWrittenEvent extends WrittenEvent
{
    const NAME = 'shop_template.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopTemplateDefinition::class;
    }
}
