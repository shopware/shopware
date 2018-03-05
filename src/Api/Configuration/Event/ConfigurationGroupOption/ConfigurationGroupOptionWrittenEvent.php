<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroupOption;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Configuration\Definition\ConfigurationGroupOptionDefinition;

class ConfigurationGroupOptionWrittenEvent extends WrittenEvent
{
    public const NAME = 'configuration_group_option.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigurationGroupOptionDefinition::class;
    }
}