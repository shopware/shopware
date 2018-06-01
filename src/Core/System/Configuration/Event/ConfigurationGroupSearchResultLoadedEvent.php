<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Configuration\Struct\ConfigurationGroupSearchResult;

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
