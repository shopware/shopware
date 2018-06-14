<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Event;

use Shopware\Core\Content\Configuration\Struct\ConfigurationGroupSearchResult;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;

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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
