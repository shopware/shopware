<?php declare(strict_types=1);

namespace Shopware\Core\System\Configuration\Event;

use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Configuration\ConfigurationGroupDefinition;

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
