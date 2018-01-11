<?php declare(strict_types=1);

namespace Shopware\Api\Config\Event\ConfigFormFieldTranslation;

use Shopware\Api\Config\Definition\ConfigFormFieldTranslationDefinition;
use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;

class ConfigFormFieldTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'config_form_field_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormFieldTranslationDefinition::class;
    }
}
