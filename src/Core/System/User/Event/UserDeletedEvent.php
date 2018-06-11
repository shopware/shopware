<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Event;

use Shopware\Core\Framework\ORM\Event\DeletedEvent;
use Shopware\Core\Framework\ORM\Event\WrittenEvent;
use Shopware\Core\System\User\UserDefinition;

class UserDeletedEvent extends WrittenEvent implements DeletedEvent
{
    public const NAME = 'user.deleted';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return UserDefinition::class;
    }
}
