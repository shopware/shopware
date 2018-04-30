<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Event\ConfigurationGroupTranslation;

use Shopware\Api\Configuration\Definition\ConfigurationGroupTranslationDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

class ConfigurationGroupTranslationWrittenEvent extends WrittenEvent
{
    public const NAME = 'configuration_group_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigurationGroupTranslationDefinition::class;
    }
}
