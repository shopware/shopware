<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigFormTranslation;

use Shopware\System\Config\Struct\ConfigFormTranslationSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormTranslationSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_translation.search.result.loaded';

    /**
     * @var ConfigFormTranslationSearchResult
     */
    protected $result;

    public function __construct(ConfigFormTranslationSearchResult $result)
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
