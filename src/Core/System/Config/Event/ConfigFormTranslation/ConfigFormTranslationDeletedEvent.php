<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigFormTranslation;

use Shopware\System\Config\Definition\ConfigFormTranslationDefinition;
use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;

class ConfigFormTranslationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'config_form_translation.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ConfigFormTranslationDefinition::class;
    }
}
