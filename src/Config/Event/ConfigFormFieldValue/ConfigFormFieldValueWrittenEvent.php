<?php declare(strict_types=1);

namespace Shopware\Config\Event\ConfigFormFieldValue;

use Shopware\Api\Write\WrittenEvent;
use Shopware\Config\Definition\ConfigFormFieldValueDefinition;

class ConfigFormFieldValueWrittenEvent extends WrittenEvent
{
    const NAME = 'config_form_field_value.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormFieldValueDefinition::class;
    }
}
