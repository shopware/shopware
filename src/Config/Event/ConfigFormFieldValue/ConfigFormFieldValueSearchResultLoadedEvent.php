<?php declare(strict_types=1);

namespace Shopware\Config\Event\ConfigFormFieldValue;

use Shopware\Config\Struct\ConfigFormFieldValueSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormFieldValueSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'config_form_field_value.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
