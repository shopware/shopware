<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigFormField;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Shop\Definition\ShopTemplateConfigFormFieldDefinition;

class ShopTemplateConfigFormFieldWrittenEvent extends WrittenEvent
{
    public const NAME = 'shop_template_config_form_field.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopTemplateConfigFormFieldDefinition::class;
    }
}
