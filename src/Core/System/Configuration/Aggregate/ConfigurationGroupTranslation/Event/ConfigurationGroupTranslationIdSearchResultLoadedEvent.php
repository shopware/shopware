<?php declare(strict_types=1);

namespace Shopware\System\Configuration\Aggregate\ConfigurationGroupTranslation\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\ORM\Search\IdSearchResult;

class ConfigurationGroupTranslationIdSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'configuration_group_translation.id.search.result.loaded';

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

    public function getContext(): Context
    {
        return $this->result->getContext();
    }

    public function getResult(): IdSearchResult
    {
        return $this->result;
    }
}
