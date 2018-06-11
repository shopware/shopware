<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Event;

use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\Content\Configuration\ConfigurationGroupDefinition;

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
