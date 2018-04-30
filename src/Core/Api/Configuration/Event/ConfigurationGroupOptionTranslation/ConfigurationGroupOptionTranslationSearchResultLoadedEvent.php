<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Event\ConfigurationGroupOptionTranslation;

use Shopware\Api\Configuration\Struct\ConfigurationGroupOptionTranslationSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
