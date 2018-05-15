<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigFormFieldTranslation;

use Shopware\System\Config\Struct\ConfigFormFieldTranslationSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormFieldTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field_translation.search.result.loaded';

    /**
     * @var ConfigFormFieldTranslationSearchResult
     */
    protected $result;

    public function __construct(ConfigFormFieldTranslationSearchResult $result)
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
