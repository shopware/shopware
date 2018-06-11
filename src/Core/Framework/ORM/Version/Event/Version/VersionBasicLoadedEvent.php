<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Version\Event\Version;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\ORM\Version\Collection\VersionBasicCollection;

class VersionBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'version.basic.loaded';

    /**
     * @var \Shopware\Core\Framework\Context
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
