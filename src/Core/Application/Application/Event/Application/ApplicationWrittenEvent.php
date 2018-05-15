<?php declare(strict_types=1);

namespace Shopware\Application\Application\Event\Application;

use Shopware\Application\Application\Definition\ApplicationDefinition;
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
