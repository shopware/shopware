<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplate;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Shop\Definition\ShopTemplateDefinition;

class ShopTemplateWrittenEvent extends WrittenEvent
{
    public const NAME = 'shop_template.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopTemplateDefinition::class;
    }
}
