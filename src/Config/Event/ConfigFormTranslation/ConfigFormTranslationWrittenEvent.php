<?php declare(strict_types=1);

namespace Shopware\Config\Event\ConfigFormTranslation;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Config\Definition\ConfigFormTranslationDefinition;

class ConfigFormTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'config_form_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormTranslationDefinition::class;
    }
}
