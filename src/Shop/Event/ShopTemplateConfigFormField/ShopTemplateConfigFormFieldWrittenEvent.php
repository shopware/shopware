<?php declare(strict_types=1);

namespace Shopware\Shop\Event\ShopTemplateConfigFormField;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Shop\Definition\ShopTemplateConfigFormFieldDefinition;

class ShopTemplateConfigFormFieldWrittenEvent extends WrittenEvent
{
    const NAME = 'shop_template_config_form_field.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopTemplateConfigFormFieldDefinition::class;
    }
}
