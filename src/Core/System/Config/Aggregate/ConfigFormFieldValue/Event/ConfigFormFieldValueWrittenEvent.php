<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormFieldValue\Event;

use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Config\Aggregate\ConfigFormFieldValue\ConfigFormFieldValueDefinition;

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
