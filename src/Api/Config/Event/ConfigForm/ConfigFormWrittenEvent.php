<?php declare(strict_types=1);

namespace Shopware\Api\Config\Event\ConfigForm;

use Shopware\Api\Config\Definition\ConfigFormDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

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
