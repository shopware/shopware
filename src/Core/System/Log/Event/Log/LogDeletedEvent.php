<?php declare(strict_types=1);

namespace Shopware\System\Log\Event\Log;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\Log\Definition\LogDefinition;

class LogDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'log.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return LogDefinition::class;
    }
}
