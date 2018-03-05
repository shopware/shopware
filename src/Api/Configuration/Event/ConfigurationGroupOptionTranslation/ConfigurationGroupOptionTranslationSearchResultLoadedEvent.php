<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroupOptionTranslation;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Api\Configuration\Struct\ConfigurationGroupOptionTranslationSearchResult;

class ConfigurationGroupOptionTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_option_translation.search.result.loaded';

    /**
     * @var ConfigurationGroupOptionTranslationSearchResult
     */
    protected $result;

    public function __construct(ConfigurationGroupOptionTranslationSearchResult $result)
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