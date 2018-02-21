<?php declare(strict_types=1);

namespace Shopware\Api\Version\Event\VersionCommit;

use Shopware\Api\Version\Collection\VersionCommitBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class VersionCommitBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'version_commit.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var VersionCommitBasicCollection
     */
    protected $versionCommits;

    public function __construct(VersionCommitBasicCollection $versionCommits, ShopContext $context)
    {
        $this->context = $context;
        $this->versionCommits = $versionCommits;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getVersionCommits(): VersionCommitBasicCollection
    {
        return $this->versionCommits;
    }
}
