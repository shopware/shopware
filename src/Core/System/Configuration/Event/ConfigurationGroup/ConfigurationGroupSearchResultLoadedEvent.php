<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Event\ConfigurationGroup;

use Shopware\System\Configuration\Struct\ConfigurationGroupSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
