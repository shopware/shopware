<?php declare(strict_types=1);

namespace Shopware\Config\Event\ConfigForm;

use Shopware\Config\Struct\ConfigFormSearchResult;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormSearchResultLoadedEvent extends NestedEvent
{
    const NAME = 'config_form.search.result.loaded';

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

    public function getContext(): TranslationContext
    {
        return $this->result->getContext();
    }
}
