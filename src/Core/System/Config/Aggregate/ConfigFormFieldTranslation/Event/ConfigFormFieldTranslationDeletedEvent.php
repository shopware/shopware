<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormFieldTranslation\Event;

use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Config\Aggregate\ConfigFormFieldTranslation\ConfigFormFieldTranslationDefinition;

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
