<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Event;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Struct\ConfigurationGroupTranslationSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

class ConfigurationGroupTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_translation.search.result.loaded';

    /**
     * @var \Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\Struct\ConfigurationGroupTranslationSearchResult
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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
