<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroupOption;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Api\Configuration\Struct\ConfigurationGroupOptionSearchResult;

class ConfigurationGroupOptionSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_option.search.result.loaded';

    /**
     * @var ConfigurationGroupOptionSearchResult
     */
    protected $result;

    public function __construct(ConfigurationGroupOptionSearchResult $result)
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