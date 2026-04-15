<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Url;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum VerificationStatus
{
    case PASS;
    case HARD_FAIL;
    case SOFT_FAIL;
}
