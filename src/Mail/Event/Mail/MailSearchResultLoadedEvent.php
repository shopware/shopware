<?php declare(strict_types=1);

namespace Shopware\Mail\Event\Mail;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Mail\Struct\MailSearchResult;

class MailSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'mail.search.result.loaded';

    /**
     * @var MailSearchResult
     */
    protected $result;

    public function __construct(MailSearchResult $result)
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
