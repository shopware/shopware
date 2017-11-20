<?php declare(strict_types=1);

namespace Shopware\Config\Event\ConfigFormFieldTranslation;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Config\Definition\ConfigFormFieldTranslationDefinition;

class ConfigFormFieldTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'config_form_field_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormFieldTranslationDefinition::class;
    }
}
