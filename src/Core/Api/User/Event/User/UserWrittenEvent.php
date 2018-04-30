<?php declare(strict_types=1);

namespace Shopware\Api\User\Event\User;

use Shopware\Api\Entity\Write\WrittenEvent;
use Shopware\Api\User\Definition\UserDefinition;

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
