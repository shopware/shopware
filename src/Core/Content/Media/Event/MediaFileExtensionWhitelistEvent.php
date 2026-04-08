<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('discovery')]
class MediaFileExtensionWhitelistEvent extends Event
{
    /**
     * @param array<string> $whitelist
     */
    public function __construct(
        private array $whitelist,
        private readonly ?Context $context = null
    ) {
        if ($context === null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Not passing $context to ' . static::class . ' is deprecated and will be required in v6.8.0.');
        }
    }

    public function getContext(): ?Context
    {
        if ($this->context === null) {
            Feature::triggerDeprecationOrThrow('v6.8.0.0', 'Not passing $context to ' . static::class . ' is deprecated and will be required in v6.8.0.');
        }

        return $this->context;
    }

    /**
     * @return array<string>
     */
    public function getWhitelist()
    {
        return $this->whitelist;
    }

    /**
     * @param array<string> $whitelist
     */
    public function setWhitelist(array $whitelist): void
    {
        $this->whitelist = $whitelist;
    }
}
