<?php declare(strict_types=1);

namespace Shopware\Core\System\UsageData\EntitySync;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('data-services')]
enum Operation: string
{
    case CREATE = 'create';
    case UPDATE = 'update';
    case DELETE = 'delete';
}
