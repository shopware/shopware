<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\Log\Package;

/**
 * The semantics of one config key of a {@see LoaderConfigSpecification}: what the stored value names.
 */
#[Package('framework')]
enum ConfigKeyKind: string
{
    case Literal = 'literal';                     // opaque value, interpreted only by the loader
    case PropertyReference = 'propertyReference'; // names an element property whose stored value feeds the loader
    case EntityName = 'entityName';               // names a registered DAL entity
}
