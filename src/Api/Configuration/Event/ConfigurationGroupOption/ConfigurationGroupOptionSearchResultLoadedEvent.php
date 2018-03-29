<?php declare(strict_types=1);

namespace Shopware\Api\Configuration\Event\ConfigurationGroupOption;

use Shopware\Api\Configuration\Struct\ConfigurationGroupOptionSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
