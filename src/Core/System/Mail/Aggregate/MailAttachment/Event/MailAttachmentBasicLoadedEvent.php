<?php declare(strict_types=1);

namespace Shopware\System\Mail\Aggregate\MailAttachment\Event;

use Shopware\System\Mail\Aggregate\MailAttachment\Collection\MailAttachmentBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class MailAttachmentBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'mail_attachment.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var MailAttachmentBasicCollection
     */
    protected $mailAttachments;

    public function __construct(MailAttachmentBasicCollection $mailAttachments, ApplicationContext $context)
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

    public function getMailAttachments(): MailAttachmentBasicCollection
    {
        return $this->mailAttachments;
    }
}
