<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Event\ConfigurationGroupOption;

use Shopware\System\Configuration\Definition\ConfigurationGroupOptionDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
