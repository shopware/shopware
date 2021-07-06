<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Event;

use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Content\Newsletter\NewsletterEvents;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\MailActionInterface;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Symfony\Contracts\EventDispatcher\Event;

class NewsletterRegisterEvent extends Event implements MailActionInterface, SalesChannelAware
{
    public const EVENT_NAME = NewsletterEvents::NEWSLETTER_REGISTER_EVENT;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var NewsletterRecipientEntity
     */
    private $newsletterRecipient;

    /**
     * @var MailRecipientStruct|null
     */
    private $mailRecipientStruct;

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $salesChannelId;

    public function __construct(
        Context $context,
        NewsletterRecipientEntity $newsletterRecipient,
        string $url,
        string $salesChannelId
    ) {
        $this->context = $context;
        $this->newsletterRecipient = $newsletterRecipient;
        $this->url = $url;
        $this->salesChannelId = $salesChannelId;
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('newsletterRecipient', new EntityType(NewsletterRecipientDefinition::class))
            ->add('url', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getNewsletterRecipient(): NewsletterRecipientEntity
    {
        return $this->newsletterRecipient;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        if (!$this->mailRecipientStruct) {
            $recipientName = $this->newsletterRecipient->getEmail();

            if ($this->newsletterRecipient->getFirstName() && $this->newsletterRecipient->getLastName()) {
                $recipientName = $this->newsletterRecipient->getFirstName() . ' ' . $this->newsletterRecipient->getLastName();
            }

            $this->mailRecipientStruct = new MailRecipientStruct(
                [
                    $this->newsletterRecipient->getEmail() => $recipientName,
                ]
            );
        }

        return $this->mailRecipientStruct;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }
}
