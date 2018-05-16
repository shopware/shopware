<?php declare(strict_types=1);

namespace Shopware\System\Config\Event;

use Shopware\System\Config\ConfigFormDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class ConfigFormWrittenEvent extends WrittenEvent
{
    public const NAME = 'config_form.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormDefinition::class;
    }
}
