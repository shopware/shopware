<?php declare(strict_types=1);

namespace Shopware\Api\Version\Event\Version;

use Shopware\Api\Version\Collection\VersionBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class VersionBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'version.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var VersionBasicCollection
     */
    protected $versions;

    public function __construct(VersionBasicCollection $versions, ApplicationContext $context)
    {
        $this->context = $context;
        $this->versions = $versions;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getVersions(): VersionBasicCollection
    {
        return $this->versions;
    }
}
