<?php declare(strict_types=1);

namespace Shopware\Core\System\Log\Event\Log;

use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\Log\LogDefinition;

class LogWrittenEvent extends WrittenEvent
{
    public const NAME = 'log.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return LogDefinition::class;
    }
}
