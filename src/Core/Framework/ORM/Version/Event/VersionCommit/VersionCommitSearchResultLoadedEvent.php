<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Version\Event\VersionCommit;

use Shopware\Framework\ORM\Version\Struct\VersionCommitSearchResult;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class VersionCommitSearchResultLoadedEvent extends NestedEvent
{
    public const NAME = 'version_commit.search.result.loaded';

    /**
     * @var VersionCommitSearchResult
     */
    protected $result;

    public function __construct(VersionCommitSearchResult $result)
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
