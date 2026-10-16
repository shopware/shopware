<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Event;

use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('framework')]
class PluginManagementEvent extends Event
{
    final public const OPERATION_EXTRACT = 'extract';
    final public const OPERATION_UPLOAD = 'upload';
    final public const OPERATION_DOWNLOAD = 'download';
    final public const OPERATION_DELETE = 'delete';

    public function __construct(
        public readonly string $operation,
        public readonly string $extensionType,
        public readonly bool $explicitType = false,
    ) {
    }
}
