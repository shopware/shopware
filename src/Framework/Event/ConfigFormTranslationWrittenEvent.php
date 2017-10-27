<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Api\Write\WrittenEvent;

class ConfigFormTranslationWrittenEvent extends WrittenEvent
{
    const NAME = 'config_form_translation.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'config_form_translation';
    }
}
