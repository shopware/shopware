<?php declare(strict_types=1);

namespace Shopware\Api\Mail\Event\MailAttachment;

use Shopware\Api\Mail\Struct\MailAttachmentSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class MailAttachmentSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'mail_attachment.search.result.loaded';

    /**
     * @var MailAttachmentSearchResult
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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
