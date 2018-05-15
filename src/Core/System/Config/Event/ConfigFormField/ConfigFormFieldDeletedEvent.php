<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigFormField;

use Shopware\System\Config\Definition\ConfigFormFieldDefinition;
use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;

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
