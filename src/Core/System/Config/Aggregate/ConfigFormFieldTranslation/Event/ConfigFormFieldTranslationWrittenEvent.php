<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Config\Aggregate\ConfigFormFieldTranslation\ConfigFormFieldTranslationDefinition;

class ConfigFormFieldTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'config_form_field_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormFieldTranslationDefinition::class;
    }
}
