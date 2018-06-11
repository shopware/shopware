<?php declare(strict_types=1);

namespace Shopware\Core\System\Log\Event\Log;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Log\Struct\LogSearchResult;

class LogSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'log.search.result.loaded';

    /**
     * @var LogSearchResult
     */
    protected $result;

    public function __construct(LogSearchResult $result)
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
