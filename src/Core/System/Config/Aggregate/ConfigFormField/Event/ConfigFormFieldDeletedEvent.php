<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormField\Event;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Config\Aggregate\ConfigFormField\ConfigFormFieldDefinition;

class ConfigFormFieldDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'config_form_field.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormFieldDefinition::class;
    }
}
