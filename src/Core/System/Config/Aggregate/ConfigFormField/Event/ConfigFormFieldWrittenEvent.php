<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormField\Event;

use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Config\Aggregate\ConfigFormField\ConfigFormFieldDefinition;

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
