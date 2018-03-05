<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroup;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Configuration\Definition\ConfigurationGroupDefinition;

class ConfigurationGroupWrittenEvent extends WrittenEvent
{
    public const NAME = 'configuration_group.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigurationGroupDefinition::class;
    }
}