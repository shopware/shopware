<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Event\ConfigurationGroupTranslation;

use Shopware\System\Configuration\Struct\ConfigurationGroupTranslationSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
