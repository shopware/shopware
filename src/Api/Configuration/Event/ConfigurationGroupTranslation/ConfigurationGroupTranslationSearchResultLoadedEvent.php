<?php

namespace Shopware\Api\Configuration\Event\ConfigurationGroupTranslation;

use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Api\Configuration\Struct\ConfigurationGroupTranslationSearchResult;

class ConfigurationGroupTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_translation.search.result.loaded';

    /**
     * @var ConfigurationGroupTranslationSearchResult
     */
    protected $result;

    public function __construct(ConfigurationGroupTranslationSearchResult $result)
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