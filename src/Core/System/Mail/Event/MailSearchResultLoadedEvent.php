<?php declare(strict_types=1);

namespace Shopware\System\Mail\Event;

use Shopware\System\Mail\Struct\MailSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
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
