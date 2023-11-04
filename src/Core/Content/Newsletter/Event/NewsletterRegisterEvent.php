<?php declare(strict_types=1);

namespace Shopware\Core\Content\Newsletter\Event;

use Shopware\Core\Content\Flow\Dispatching\Action\FlowMailVariables;
use Shopware\Core\Content\Flow\Dispatching\Aware\NewsletterRecipientAware;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Content\Flow\Dispatching\Aware\UrlAware;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EntityType;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\MailAware;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @deprecated tag:v6.6.0 - reason:class-hierarchy-change - UrlAware is deprecated and will be removed in v6.6.0
 */
#[Package('customer-order')]
class NewsletterRegisterEvent extends Event implements SalesChannelAware, MailAware, NewsletterRecipientAware, UrlAware, ScalarValuesAware, FlowEventAware
{
    final public const EVENT_NAME = 'newsletter.register';

    private ?MailRecipientStruct $mailRecipientStruct = null;

    public function __construct(
        private readonly Context $context,
        private readonly NewsletterRecipientEntity $newsletterRecipient,
        private readonly string $url,
        private readonly string $salesChannelId
    ) {
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('newsletterRecipient', new EntityType(NewsletterRecipientDefinition::class))
            ->add('url', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }

    /**
     * @return array<string, scalar|array<mixed>|null>
     */
    public function getValues(): array
    {
        return [FlowMailVariables::URL => $this->url];
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

    public function getNewsletterRecipientId(): string
    {
        return $this->newsletterRecipient->getId();
    }
}
