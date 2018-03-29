<?php

namespace Shopware\Api\Application\Event\Application;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Application\Definition\ApplicationDefinition;

class ApplicationWrittenEvent extends WrittenEvent
{
    public const NAME = 'application.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return ApplicationDefinition::class;
    }
}