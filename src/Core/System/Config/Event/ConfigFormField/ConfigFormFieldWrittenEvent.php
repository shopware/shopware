<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigFormField;

use Shopware\System\Config\Definition\ConfigFormFieldDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

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
