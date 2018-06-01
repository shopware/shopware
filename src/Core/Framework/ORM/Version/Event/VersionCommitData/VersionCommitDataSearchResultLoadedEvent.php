<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Version\Event\VersionCommitData;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\ORM\Version\Struct\VersionCommitDataSearchResult;
use Shopware\Core\Framework\ORM\Version\Struct\VersionCommitSearchResult;

class VersionCommitDataSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'version_commit_data.search.result.loaded';

    /**
     * @var VersionCommitSearchResult
     */
    protected $result;

    public function __construct(VersionCommitDataSearchResult $result)
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
}
