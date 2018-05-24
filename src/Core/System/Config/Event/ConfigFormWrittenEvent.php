<?php declare(strict_types=1);

namespace Shopware\System\Config\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Config\ConfigFormDefinition;

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
