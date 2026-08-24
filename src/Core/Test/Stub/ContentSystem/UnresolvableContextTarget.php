<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\Log\Package;

/**
 * A reference-property target FQCN that no data loader produces and no source provides as root-ambient
 * context. A required element-type property of this type is therefore unresolvable against every binding,
 * which is exactly what the resolvability-gate tests need to force a binding-scope violation.
 *
 * @final
 */
#[Package('framework')]
class UnresolvableContextTarget
{
}
