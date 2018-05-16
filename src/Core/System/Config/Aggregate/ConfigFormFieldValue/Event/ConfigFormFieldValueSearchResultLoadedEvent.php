<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormFieldValue\Event;

use Shopware\System\Config\Aggregate\ConfigFormFieldValue\Struct\ConfigFormFieldValueSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormFieldValueSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field_value.search.result.loaded';

    /**
     * @var ConfigFormFieldValueSearchResult
     */
    protected $result;

    public function __construct(ConfigFormFieldValueSearchResult $result)
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
