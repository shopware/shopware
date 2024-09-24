<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Struct\ExtensionCollection;
use Shopware\Core\Framework\Update\Services\ExtensionCompatibility;
use Shopware\Core\Framework\Update\Struct\Version;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @phpstan-import-type Compatibility from ExtensionCompatibility
 */
#[Package('services-settings')]
class ExtensionCompatibilitiesResolvedEvent extends Event
{
    /**
     * @param list<Compatibility> $compatibilities
     */
    public function __construct(
        public Version $update,
        public ExtensionCollection $extensions,
        public array $compatibilities,
        public readonly Context $context
    ) {
    }
}
