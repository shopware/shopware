<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Event\ConfigurationGroupOption;

use Shopware\System\Configuration\Definition\ConfigurationGroupOptionDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

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
