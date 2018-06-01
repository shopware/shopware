<?php declare(strict_types=1);

namespace Shopware\Framework\Plugin\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Plugin\Struct\PluginSearchResult;

class PluginSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'plugin.search.result.loaded';

    /**
     * @var PluginSearchResult
     */
    protected $result;

    public function __construct(PluginSearchResult $result)
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
