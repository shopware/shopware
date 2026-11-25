<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Resolution;

use Shopware\Core\Framework\Log\Package;

/**
 * The resolution of a single declared property of an element type at a position: how it is (or is not) filled.
 * `type` and `fqcn` are mutually exclusive by `kind`: a `Primitive` resolution never carries `fqcn` and carries
 * `type` only when the declared type is a single name, and a `Reference` resolution carries `fqcn` and never `type`.
 *
 * @internal
 */
#[Package('framework')]
final readonly class PropertyResolution
{
    /**
     * @param list<ResolutionCandidate> $candidates
     */
    public function __construct(
        public string $key,
        public PropertyKind $kind,
        public bool $required,
        public ?string $type = null,
        public mixed $default = null,
        public ?string $fqcn = null,
        public ?ResolutionCandidate $resolved = null,
        public array $candidates = [],
    ) {
    }
}
