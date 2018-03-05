<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroup;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Configuration\Definition\ConfigurationGroupDefinition;

class ConfigurationGroupDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'configuration_group.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigurationGroupDefinition::class;
    }
}
