<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Recovery;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Symfony\Contracts\EventDispatcher\Event;

class UserRecoveryRequestEvent extends Event
{
    public const EVENT_NAME = 'user.recovery.request.event';

    /**
     * @var UserRecoveryEntity
     */
    private $userRecovery;

    /**
     * @var Context
     */
    private $context;

    public function __construct(UserRecoveryEntity $userRecovery, Context $context)
    {
        $this->userRecovery = $userRecovery;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getUserRecovery(): UserRecoveryEntity
    {
        return $this->userRecovery;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('userRecovery', new EntityType(UserRecoveryDefinition::class));
    }
}
