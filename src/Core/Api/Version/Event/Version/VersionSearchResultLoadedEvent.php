<?php declare(strict_types=1);

namespace Shopware\Api\Version\Event\Version;

use Shopware\Api\Version\Struct\VersionSearchResult;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class VersionSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'version.search.result.loaded';

    /**
     * @var VersionSearchResult
     */
    protected $result;

    public function __construct(VersionSearchResult $result)
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
