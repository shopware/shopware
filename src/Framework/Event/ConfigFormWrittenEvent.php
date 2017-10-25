<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\EntityWrittenEvent;

class ConfigFormWrittenEvent extends EntityWrittenEvent
{
    const NAME = 'config_form.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'config_form';
    }
}
