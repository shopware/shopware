<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigPreset;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Shop\Definition\ShopTemplateConfigPresetDefinition;

class ShopTemplateConfigPresetWrittenEvent extends WrittenEvent
{
    public const NAME = 'shop_template_config_preset.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ShopTemplateConfigPresetDefinition::class;
    }
}
