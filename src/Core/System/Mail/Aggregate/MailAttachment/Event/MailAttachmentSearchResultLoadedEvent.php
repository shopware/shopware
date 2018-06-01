<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Aggregate\MailAttachment\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Mail\Aggregate\MailAttachment\Struct\MailAttachmentSearchResult;

class MailAttachmentSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'mail_attachment.search.result.loaded';

    /**
     * @var \Shopware\Core\System\Mail\Aggregate\MailAttachment\Struct\MailAttachmentSearchResult
     */
    protected $result;

    public function __construct(MailAttachmentSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
