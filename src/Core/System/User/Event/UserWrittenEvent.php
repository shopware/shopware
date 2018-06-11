<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Event;

use Shopware\Core\Framework\ORM\Write\WrittenEvent;
use Shopware\Core\System\User\UserDefinition;

class UserWrittenEvent extends WrittenEvent
{
    public const NAME = 'user.written';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDefinition(): string
    {
        return UserDefinition::class;
    }
}
