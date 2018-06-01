<?php declare(strict_types=1);

namespace Shopware\System\Mail\Aggregate\MailAttachment\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Mail\Aggregate\MailAttachment\Collection\MailAttachmentBasicCollection;

class MailAttachmentBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'mail_attachment.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var MailAttachmentBasicCollection
     */
    protected $mailAttachments;

    public function __construct(MailAttachmentBasicCollection $mailAttachments, Context $context)
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

    public function getMailAttachments(): MailAttachmentBasicCollection
    {
        return $this->mailAttachments;
    }
}
