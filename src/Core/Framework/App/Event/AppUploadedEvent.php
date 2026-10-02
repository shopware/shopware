<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class AppUploadedEvent extends Event
{
    public function __construct(
        public readonly string $filename,
        public readonly Context $context,
        public readonly ?string $appName = null,
        public readonly ?string $appVersion = null
    ) {
    }
}
