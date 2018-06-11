<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailAttachment\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Media\Event\MediaBasicLoadedEvent;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\System\Mail\Aggregate\MailAttachment\Collection\MailAttachmentDetailCollection;
use Shopware\Core\System\Mail\Event\MailBasicLoadedEvent;

class MailAttachmentDetailLoadedEvent extends NestedEvent
{
    public const NAME = 'mail_attachment.detail.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
     */
    protected $context;

    /**
     * @var MailAttachmentDetailCollection
     */
    protected $mailAttachments;

    public function __construct(MailAttachmentDetailCollection $mailAttachments, Context $context)
    {
        $this->context = $context;
        $this->mailAttachments = $mailAttachments;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
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

        return new NestedEventCollection($events);
    }
}
