<?php declare(strict_types=1);

namespace Shopware\Log\Event\Log;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Log\Struct\LogSearchResult;

class LogSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'log.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
