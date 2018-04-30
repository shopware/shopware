<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Event\ConfigurationGroup;

use Shopware\Api\Configuration\Definition\ConfigurationGroupDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

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
