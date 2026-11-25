<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum VersionHistoryEntryType: string
{
    case INSTALL = 'install';
    case UPDATE = 'update';
}
