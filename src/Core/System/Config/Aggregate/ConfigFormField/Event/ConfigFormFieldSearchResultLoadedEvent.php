<?php declare(strict_types=1);

namespace Shopware\Core\System\Config\Aggregate\ConfigFormField\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\System\Config\Aggregate\ConfigFormField\Struct\ConfigFormFieldSearchResult;

class ConfigFormFieldSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field.search.result.loaded';

    /**
     * @var \Shopware\Core\System\Config\Aggregate\ConfigFormField\Struct\ConfigFormFieldSearchResult
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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }
}
