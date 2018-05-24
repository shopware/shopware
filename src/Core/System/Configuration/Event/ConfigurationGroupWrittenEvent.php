<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Configuration\ConfigurationGroupDefinition;

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
