<?php declare(strict_types=1);

namespace Shopware\Core\System\Log\Event\Log;

use Shopware\Core\Framework\ORM\Write\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\Log\LogDefinition;

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
