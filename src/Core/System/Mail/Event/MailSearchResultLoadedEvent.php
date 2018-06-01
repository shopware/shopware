<?php declare(strict_types=1);

namespace Shopware\Core\System\Mail\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Mail\Struct\MailSearchResult;

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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
