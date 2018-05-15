<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigFormField;

use Shopware\System\Config\Struct\ConfigFormFieldSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormFieldSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field.search.result.loaded';

    /**
     * @var ConfigFormFieldSearchResult
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
