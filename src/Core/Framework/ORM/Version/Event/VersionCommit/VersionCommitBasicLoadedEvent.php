<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Version\Event\VersionCommit;

use Shopware\Framework\ORM\Version\Collection\VersionCommitBasicCollection;
use Shopware\Application\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class VersionCommitBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'version_commit.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var VersionCommitBasicCollection
     */
    protected $versionCommits;

    public function __construct(VersionCommitBasicCollection $versionCommits, ApplicationContext $context)
    {
        $this->context = $context;
        $this->versionCommits = $versionCommits;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getVersionCommits(): VersionCommitBasicCollection
    {
        return $this->versionCommits;
    }
}
