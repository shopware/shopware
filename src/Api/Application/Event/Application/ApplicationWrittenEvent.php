<?php declare(strict_types=1);

namespace Shopware\Api\Application\Event\Application;

use Shopware\Api\Application\Definition\ApplicationDefinition;
use Shopware\Api\Entity\Write\WrittenEvent;

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
