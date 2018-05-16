<?php declare(strict_types=1);

namespace Shopware\Application\Application\Event;

use Shopware\Application\Application\ApplicationDefinition;
use Shopware\Framework\ORM\Write\WrittenEvent;

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
