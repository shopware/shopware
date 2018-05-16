<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormField\Event;

use Shopware\System\Config\Aggregate\ConfigFormField\ConfigFormFieldDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

class ConfigFormFieldWrittenEvent extends WrittenEvent
{
    public const NAME = 'config_form_field.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormFieldDefinition::class;
    }
}
