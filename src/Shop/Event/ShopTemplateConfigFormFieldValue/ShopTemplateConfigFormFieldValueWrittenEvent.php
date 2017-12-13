<?php declare(strict_types=1);

namespace Shopware\Shop\Event\ShopTemplateConfigFormFieldValue;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Shop\Definition\ShopTemplateConfigFormFieldValueDefinition;

class ShopTemplateConfigFormFieldValueWrittenEvent extends WrittenEvent
{
    const NAME = 'shop_template_config_form_field_value.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopTemplateConfigFormFieldValueDefinition::class;
    }
}
