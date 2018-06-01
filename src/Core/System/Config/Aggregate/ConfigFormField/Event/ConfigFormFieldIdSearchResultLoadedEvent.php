<?php declare(strict_types=1);

namespace Shopware\System\Config\Aggregate\ConfigFormField\Event;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\ORM\Search\IdSearchResult;

class ConfigFormFieldIdSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'config_form_field.id.search.result.loaded';

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
