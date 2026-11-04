<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class PluginUploadedEvent extends Event
{
    public function __construct(
        public readonly string $filename,
        public readonly Context $context,
        public readonly ?string $pluginName = null,
        public readonly ?string $pluginVersion = null
    ) {
    }
}
