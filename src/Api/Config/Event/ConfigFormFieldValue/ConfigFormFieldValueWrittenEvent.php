<?php declare(strict_types=1);

namespace Shopware\Api\Config\Event\ConfigFormFieldValue;

use Shopware\Api\Config\Definition\ConfigFormFieldValueDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

class ConfigFormFieldValueWrittenEvent extends WrittenEvent
{
    public const NAME = 'config_form_field_value.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormFieldValueDefinition::class;
    }
}
