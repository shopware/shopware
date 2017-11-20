<?php declare(strict_types=1);

namespace Shopware\Config\Event\ConfigFormField;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Config\Definition\ConfigFormFieldDefinition;

class ConfigFormFieldWrittenEvent extends WrittenEvent
{
    const NAME = 'config_form_field.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormFieldDefinition::class;
    }
}
