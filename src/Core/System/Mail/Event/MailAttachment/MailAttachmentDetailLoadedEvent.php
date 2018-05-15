<?php declare(strict_types=1);

namespace Shopware\System\Mail\Event\MailAttachment;

use Shopware\System\Mail\Collection\MailAttachmentDetailCollection;
use Shopware\System\Mail\Event\Mail\MailBasicLoadedEvent;
use Shopware\Api\Media\Event\Media\MediaBasicLoadedEvent;
use Shopware\Api\Shop\Event\Shop\ShopBasicLoadedEvent;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;

class MailAttachmentDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'mail_attachment.detail.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var MailAttachmentDetailCollection
     */
    protected $mailAttachments;

    public function __construct(MailAttachmentDetailCollection $mailAttachments, ApplicationContext $context)
    {
        $this->context = $context;
        $this->mailAttachments = $mailAttachments;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getMailAttachments(): MailAttachmentDetailCollection
    {
        return $this->mailAttachments;
    }

    public function getEvents(): ?NestedEventCollection
    {
        $events = [];
        if ($this->mailAttachments->getMails()->count() > 0) {
            $events[] = new MailBasicLoadedEvent($this->mailAttachments->getMails(), $this->context);
        }
        if ($this->mailAttachments->getMedia()->count() > 0) {
            $events[] = new MediaBasicLoadedEvent($this->mailAttachments->getMedia(), $this->context);
        }
        if ($this->mailAttachments->getShops()->count() > 0) {
            $events[] = new ShopBasicLoadedEvent($this->mailAttachments->getShops(), $this->context);
        }

        return new NestedEventCollection($events);
    }
}
