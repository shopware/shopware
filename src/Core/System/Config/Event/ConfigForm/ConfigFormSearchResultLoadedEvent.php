<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigForm;

use Shopware\System\Config\Struct\ConfigFormSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

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

    public function getContext(): ApplicationContext
    {
        return $this->result->getContext();
    }
}
