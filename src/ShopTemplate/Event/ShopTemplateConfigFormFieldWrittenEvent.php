<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Event;

use Shopware\Api\Write\WrittenEvent;

class ShopTemplateConfigFormFieldWrittenEvent extends WrittenEvent
{
    const NAME = 'shop_template_config_form_field.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'shop_template_config_form_field';
    }
}
