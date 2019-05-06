<?php declare(strict_types=1);

namespace Shopware\Core\Content\NewsletterReceiver\Event;

use Shopware\Core\Content\NewsletterReceiver\NewsletterReceiverDefinition;
use Shopware\Core\Content\NewsletterReceiver\NewsletterReceiverEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\MailActionInterface;
use Symfony\Component\EventDispatcher\Event;

class NewsletterConfirmEvent extends Event implements BusinessEventInterface, MailActionInterface
{
    public const EVENT_NAME = 'newsletter.confirm';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var NewsletterReceiverEntity
     */
    private $receiverEntity;

    /**
     * @var MailRecipientStruct|null
     */
    private $mailRecipientStruct;

    /**
     * @var string
     */
    private $salesChannelId;

    public function __construct(Context $context, NewsletterReceiverEntity $receiverEntity, string $salesChannelId)
    {
        $this->context = $context;
        $this->receiverEntity = $receiverEntity;
        $this->salesChannelId = $salesChannelId;
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('receiverEntity', new EntityType(NewsletterReceiverDefinition::class));
    }

    public function getReceiverEntity(): NewsletterReceiverEntity
    {
        return $this->receiverEntity;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if ($this->mailRecipientStruct) {
            return $this->mailRecipientStruct;
        }

        return new MailRecipientStruct(
            [
                $this->receiverEntity->getEmail() => $this->receiverEntity->getFirstName() . ' ' . $this->receiverEntity->getLastName(),
            ]
        );
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }
}
