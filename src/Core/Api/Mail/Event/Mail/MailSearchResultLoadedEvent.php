<?php declare(strict_types=1);

namespace Shopware\Api\Mail\Event\Mail;

use Shopware\Api\Mail\Struct\MailSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class MailSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'mail.search.result.loaded';

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
