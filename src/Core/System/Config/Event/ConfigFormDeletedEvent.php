<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Event;

use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Config\ConfigFormDefinition;

class ConfigFormDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'config_form.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormDefinition::class;
    }
}
