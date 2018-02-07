<?php declare(strict_types=1);

namespace Shopware\Api\Version\Event\Version;

use Shopware\Api\Version\Collection\VersionBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class VersionBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'version.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var VersionBasicCollection
     */
    protected $versions;

    public function __construct(VersionBasicCollection $versions, TranslationContext $context)
    {
        $this->context = $context;
        $this->versions = $versions;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getVersions(): VersionBasicCollection
    {
        return $this->versions;
    }
}
