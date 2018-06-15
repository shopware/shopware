<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\Event;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption\Struct\ConfigurationGroupOptionSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
