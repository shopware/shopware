<?php declare(strict_types=1);

namespace Shopware\Api\Log\Event\Log;

use Shopware\Api\Entity\Write\DeletedEvent;
use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\Log\Definition\LogDefinition;

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
