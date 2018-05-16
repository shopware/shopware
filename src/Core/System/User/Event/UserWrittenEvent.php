<?php declare(strict_types=1);

namespace Shopware\System\User\Event;

use Shopware\Framework\ORM\Write\WrittenEvent;
use Shopware\System\User\UserDefinition;

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
