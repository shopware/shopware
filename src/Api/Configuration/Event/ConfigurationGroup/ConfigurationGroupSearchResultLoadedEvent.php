<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroup;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Api\Configuration\Struct\ConfigurationGroupSearchResult;

class ConfigurationGroupSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group.search.result.loaded';

    /**
     * @var ConfigurationGroupSearchResult
     */
    protected $result;

    public function __construct(ConfigurationGroupSearchResult $result)
    {
        $this->result = $result;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->result->getContext();
    }
}