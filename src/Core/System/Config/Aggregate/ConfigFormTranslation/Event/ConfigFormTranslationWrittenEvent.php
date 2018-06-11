<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\Event;

use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\ConfigFormTranslationDefinition;

class ConfigFormTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'config_form_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormTranslationDefinition::class;
    }
}
