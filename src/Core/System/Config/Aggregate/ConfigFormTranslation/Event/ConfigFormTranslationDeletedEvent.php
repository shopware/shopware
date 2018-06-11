<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\Event;

use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Config\Aggregate\ConfigFormTranslation\ConfigFormTranslationDefinition;

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
