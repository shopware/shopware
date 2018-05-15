<?php declare(strict_types=1);

namespace Shopware\System\Config\Event\ConfigFormTranslation;

use Shopware\Framework\ORM\Search\IdSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ConfigFormTranslationIdSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_translation.id.search.result.loaded';

    /**
     * @var IdSearchResult
     */
    protected $result;

    public function __construct(IdSearchResult $result)
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

    public function getResult(): IdSearchResult
    {
        return $this->result;
    }
}
