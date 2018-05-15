<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigFormField;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Api\Shop\Definition\ShopTemplateConfigFormFieldDefinition;

class ShopTemplateConfigFormFieldDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'shop_template_config_form_field.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopTemplateConfigFormFieldDefinition::class;
    }
}
