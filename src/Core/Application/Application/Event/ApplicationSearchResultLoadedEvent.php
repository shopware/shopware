<?php declare(strict_types=1);

namespace Shopware\Application\Application\Event;

use Shopware\Application\Application\Struct\ApplicationSearchResult;
use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;

class ApplicationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'application.search.result.loaded';

    /**
     * @var ApplicationSearchResult
     */
    protected $result;

    public function __construct(ApplicationSearchResult $result)
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
