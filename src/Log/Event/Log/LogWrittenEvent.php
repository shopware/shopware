<?php declare(strict_types=1);

namespace Shopware\Log\Event\Log;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Log\Definition\LogDefinition;

class LogWrittenEvent extends WrittenEvent
{
    const NAME = 'log.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return LogDefinition::class;
    }
}
