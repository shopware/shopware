<?php

namespace Shopware\Api\Application\Event\Application;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Application\Definition\ApplicationDefinition;

class ApplicationDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'application.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ApplicationDefinition::class;
    }
}
