<?php declare(strict_types=1);

namespace Shopware\Mail\Event\MailAttachment;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Mail\Struct\MailAttachmentSearchResult;

class MailAttachmentSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'mail_attachment.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
