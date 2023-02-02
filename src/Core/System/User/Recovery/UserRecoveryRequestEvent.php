<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Recovery;

use Shopware\Core\Content\Flow\Dispatching\Aware\ResetUrlAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\UserAware;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Shopware\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Shopware\Core\System\User\UserEntity;
use Symfony\Contracts\EventDispatcher\Event;

class UserRecoveryRequestEvent extends Event implements BusinessEventInterface, UserAware, MailAware, ResetUrlAware
{
    public const EVENT_NAME = 'user.recovery.request';

    /**
     * @var UserRecoveryEntity
     */
    private $userRecovery;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $resetUrl;

    /**
     * @var MailRecipientStruct
     */
    private $mailRecipientStruct;

    public function __construct(UserRecoveryEntity $userRecovery, string $resetUrl, Context $context)
    {
        $this->userRecovery = $userRecovery;
        $this->context = $context;
        $this->resetUrl = $resetUrl;
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
            ->add('userRecovery', new EntityType(UserRecoveryDefinition::class))
            ->add('resetUrl', new ScalarValueType('string'))
        ;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct instanceof MailRecipientStruct) {
            /** @var UserEntity $user */
            $user = $this->userRecovery->getUser();

            $this->mailRecipientStruct = new MailRecipientStruct([
                $user->getEmail() => $user->getFirstName() . ' ' . $user->getLastName(),
            ]);
        }

        return $this->mailRecipientStruct;
    }

    public function getSalesChannelId(): ?string
    {
        return null;
    }

    public function getResetUrl(): string
    {
        return $this->resetUrl;
    }

    public function getUserId(): string
    {
        return $this->getUserRecovery()->getId();
    }
}
