<?php declare(strict_types=1);

namespace Shopware\Shop\Event\ShopTemplateConfigPreset;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Shop\Definition\ShopTemplateConfigPresetDefinition;

class ShopTemplateConfigPresetWrittenEvent extends WrittenEvent
{
    const NAME = 'shop_template_config_preset.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopTemplateConfigPresetDefinition::class;
    }
}
