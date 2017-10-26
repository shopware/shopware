<?php declare(strict_types=1);

namespace Shopware\Framework\Event;

use Shopware\Framework\Write\AbstractWrittenEvent;

class AttributeConfigurationWrittenEvent extends AbstractWrittenEvent
{
    const NAME = 'attribute_configuration.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getEntityName(): string
    {
        return 'attribute_configuration';
    }
}
