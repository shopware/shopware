<?php declare(strict_types=1);

namespace Shopware\System\User\Event\User;

use Shopware\Framework\ORM\Write\DeletedEvent;
use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\User\Definition\UserDefinition;

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
