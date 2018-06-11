<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\Event;

use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;

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
