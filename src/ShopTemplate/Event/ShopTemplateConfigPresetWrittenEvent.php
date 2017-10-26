<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class ShopTemplateConfigPresetWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'shop_template_config_preset.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'shop_template_config_preset';
    }
}
