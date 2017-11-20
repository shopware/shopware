<?php declare(strict_types=1);

namespace Shopware\Config\Event\ConfigFormField;

use Shopware\Config\Struct\ConfigFormFieldSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormFieldSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'config_form_field.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
