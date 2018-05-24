<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormField\Event;

use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\System\Config\Aggregate\ConfigFormField\Struct\ConfigFormFieldSearchResult;

class ConfigFormFieldSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field.search.result.loaded';

    /**
     * @var \Shopware\System\Config\Aggregate\ConfigFormField\Struct\ConfigFormFieldSearchResult
     */
    protected $result;

    public function __construct(ConfigFormFieldSearchResult $result)
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
