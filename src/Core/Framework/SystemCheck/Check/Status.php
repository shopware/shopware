<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck\Check;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('core')]
enum Status
{
    case OK;
    case UNKNOWN;

    case SKIPPED;

    case WARNING;

    case ERROR;

    case FAILURE;
}
