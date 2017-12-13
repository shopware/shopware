<?php declare(strict_types=1);

namespace Shopware\Config\Event\ConfigForm;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Config\Definition\ConfigFormDefinition;

class ConfigFormWrittenEvent extends WrittenEvent
{
    const NAME = 'config_form.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormDefinition::class;
    }
}
