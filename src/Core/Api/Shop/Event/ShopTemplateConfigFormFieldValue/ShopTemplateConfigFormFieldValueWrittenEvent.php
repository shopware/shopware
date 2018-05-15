<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\Api\Shop\Definition\ShopTemplateConfigFormFieldValueDefinition;

class ShopTemplateConfigFormFieldValueWrittenEvent extends WrittenEvent
{
    public const NAME = 'shop_template_config_form_field_value.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopTemplateConfigFormFieldValueDefinition::class;
    }
}
