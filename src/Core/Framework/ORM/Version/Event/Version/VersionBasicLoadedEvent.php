<?php declare(strict_types=1);

namespace Shopware\Framework\ORM\Version\Event\Version;

use Shopware\Framework\Context;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\ORM\Version\Collection\VersionBasicCollection;

class VersionBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'version.basic.loaded';

    /**
     * @var \Shopware\Framework\Context
     */
    protected $context;

    /**
     * @var VersionBasicCollection
     */
    protected $versions;

    public function __construct(VersionBasicCollection $versions, Context $context)
    {
        $this->context = $context;
        $this->versions = $versions;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getVersions(): VersionBasicCollection
    {
        return $this->versions;
    }
}
