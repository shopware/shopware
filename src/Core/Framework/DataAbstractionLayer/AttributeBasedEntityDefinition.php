<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer;

use Shopware\Core\Framework\Log\Package;

/**
 * Marker interface for entity definitions that are constructed from PHP attributes
 * and require metadata injection rather than direct instantiation.
 *
 * Implementations get their entity name from the service tag 'entity' attribute
 * enabling container caching without metadata reconstruction.
 *
 * @internal
 */
#[Package('framework')]
interface AttributeBasedEntityDefinition
{
}
