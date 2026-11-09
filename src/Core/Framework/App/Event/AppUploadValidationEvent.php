<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class AppUploadValidationEvent extends Event
{
    public function __construct(public readonly string $zipPath)
    {
    }
}
