<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Event;

use Shopware\Api\Write\WrittenEvent;

class ShopTemplateConfigFormWrittenEvent extends WrittenEvent
{
    const NAME = 'shop_template_config_form.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'shop_template_config_form';
    }
}
