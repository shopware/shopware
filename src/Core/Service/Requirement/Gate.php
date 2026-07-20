<?php declare(strict_types=1);

namespace Shopware\Core\Service\Requirement;

use Shopware\Core\Framework\Log\Package;

/**
 * Which lifecycle decision a {@see ServiceRequirement} gates: whether a service may exist
 * (installed vs uninstalled), whether an installed service may run (privileges granted vs revoked),
 * or nothing at all — {@see Gate::NONE} is a recognised marker requirement that gates neither.
 *
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('framework')]
enum Gate
{
    case INSTALLATION;
    case PRIVILEGES;
    case NONE;
}
