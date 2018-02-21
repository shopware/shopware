<?php declare(strict_types=1);

namespace Shopware\Api\Version\Event\VersionCommitData;

use Shopware\Api\Version\Collection\VersionCommitDataBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class VersionCommitDataBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'version_commit_data.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var VersionCommitDataBasicCollection
     */
    protected $versionChanges;

    public function __construct(VersionCommitDataBasicCollection $versionChanges, ShopContext $context)
    {
        $this->context = $context;
        $this->versionChanges = $versionChanges;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getVersionChanges(): VersionCommitDataBasicCollection
    {
        return $this->versionChanges;
    }
}
