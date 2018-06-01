<?php declare(strict_types=1);

namespace Shopware\System\Config\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Config\Struct\ConfigFormSearchResult;

class ConfigFormSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form.search.result.loaded';

    /**
     * @var ConfigFormSearchResult
     */
    protected $result;

    public function __construct(ConfigFormSearchResult $result)
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
