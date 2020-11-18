<?php declare(strict_types=1);

namespace Shopware\Core\Content\ContactForm\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventInterface;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Event\EventData\ObjectType;
use Shopware\Core\Framework\Event\MailActionInterface;
use Shopware\Core\Framework\Event\SalesChannelAware;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Symfony\Contracts\EventDispatcher\Event;

final class ContactFormEvent extends Event implements BusinessEventInterface, MailActionInterface, SalesChannelAware
{
    public const EVENT_NAME = 'contact_form.send';

    /**
     * @var Context
     */
    private $context;

    /**
     * @var string
     */
    private $salesChannelId;

    /**
     * @var MailRecipientStruct
     */
    private $recipients;

    /**
     * @var array
     */
    private $contactFormData;

    public function __construct(Context $context, string $salesChannelId, MailRecipientStruct $recipients, DataBag $contactFormData)
    {
        $this->context = $context;
        $this->salesChannelId = $salesChannelId;
        $this->recipients = $recipients;
        $this->contactFormData = $contactFormData->all();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('contactFormData', new ObjectType());
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getMailStruct(): MailRecipientStruct
    {
        return $this->recipients;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getContactFormData(): array
    {
        return $this->contactFormData;
    }
}
