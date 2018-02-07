<?php declare(strict_types=1);

namespace Shopware\Api\Version\Event\VersionCommitData;

use Shopware\Api\Version\Collection\VersionCommitBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class VersionCommitDataBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'version_commit_data.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var VersionCommitBasicCollection
     */
    protected $versionChanges;

    public function __construct(VersionCommitBasicCollection $versionChanges, TranslationContext $context)
    {
        $this->context = $context;
        $this->versionChanges = $versionChanges;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getVersionChanges(): VersionCommitBasicCollection
    {
        return $this->versionChanges;
    }
}
