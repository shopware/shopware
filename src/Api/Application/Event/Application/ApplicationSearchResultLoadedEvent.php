<?php

namespace Shopware\Api\Application\Event\Application;

use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Api\Application\Struct\ApplicationSearchResult;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}