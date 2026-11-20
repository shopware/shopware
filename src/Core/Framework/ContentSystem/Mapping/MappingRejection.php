<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\Log\Package;

/**
 * An unavailable mapping or incompatible runtime value cannot use authored fallback.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
enum MappingRejection
{
    case Unavailable;
    case Incompatible;
}
