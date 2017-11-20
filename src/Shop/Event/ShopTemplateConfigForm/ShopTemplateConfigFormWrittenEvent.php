<?php declare(strict_types=1);

namespace Shopware\Shop\Event\ShopTemplateConfigForm;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Shop\Definition\ShopTemplateConfigFormDefinition;

class ShopTemplateConfigFormWrittenEvent extends WrittenEvent
{
    const NAME = 'shop_template_config_form.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopTemplateConfigFormDefinition::class;
    }
}
