<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplate;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Api\Shop\Definition\ShopTemplateDefinition;

class ShopTemplateDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'shop_template.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopTemplateDefinition::class;
    }
}
