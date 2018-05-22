<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormFieldValue\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Config\Aggregate\ConfigFormFieldValue\ConfigFormFieldValueDefinition;

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
