<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigFormField;

use Shopware\System\Config\Definition\ConfigFormFieldDefinition;
use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
