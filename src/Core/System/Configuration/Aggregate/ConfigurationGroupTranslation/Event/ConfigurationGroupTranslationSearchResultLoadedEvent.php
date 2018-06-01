<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Aggregate\ConfigurationGroupTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Configuration\Aggregate\ConfigurationGroupTranslation\Struct\ConfigurationGroupTranslationSearchResult;

class ConfigurationGroupTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_translation.search.result.loaded';

    /**
     * @var \Shopware\System\Configuration\Aggregate\ConfigurationGroupTranslation\Struct\ConfigurationGroupTranslationSearchResult
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
