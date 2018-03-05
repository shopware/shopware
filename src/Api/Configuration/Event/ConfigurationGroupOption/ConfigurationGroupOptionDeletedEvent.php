<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroupOption;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Configuration\Definition\ConfigurationGroupOptionDefinition;

class ConfigurationGroupOptionDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'configuration_group_option.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigurationGroupOptionDefinition::class;
    }
}
