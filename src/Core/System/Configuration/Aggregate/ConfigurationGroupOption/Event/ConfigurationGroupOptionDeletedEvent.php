<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupOption\ConfigurationGroupOptionDefinition;

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
