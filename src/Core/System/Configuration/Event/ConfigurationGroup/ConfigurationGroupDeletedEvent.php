<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Event\ConfigurationGroup;

use Shopware\System\Configuration\Definition\ConfigurationGroupDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
