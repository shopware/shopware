<?php declare(strict_types=1);

namespace Shopware\System\Log\Event\Log;

use Shopware\System\Log\Struct\LogSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
