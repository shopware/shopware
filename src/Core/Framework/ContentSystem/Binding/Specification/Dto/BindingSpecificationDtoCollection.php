<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto;

use Shopware\Core\Framework\ContentSystem\Binding\Serialization\BindingSpecificationCanonicalizer;
use Shopware\Core\Framework\ContentSystem\Binding\Validation\TypeConsistentBindingSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Keyed by id (e.g. `Sw:Media:Image`) so Symfony includes the id in violation property paths:
 * `bindings[Sw:Media:Image].type`.
 *
 * Carries the semantic constraint {@see TypeConsistentBindingSpecification} at the collection level (not on the
 * DTO) so a per-load type overlay can ride the validated object into the validator: a per-call overlay cannot be
 * passed through the dependency-injected `ValidatorInterface`, but it can be a field the constraint's validator
 * reads. `WellFormedBindingSpecification` stays class-level on the DTO and runs via the `#[Assert\Valid]` cascade.
 *
 * @internal
 */
#[Package('framework')]
#[TypeConsistentBindingSpecification]
final readonly class BindingSpecificationDtoCollection
{
    /**
     * @param array<string, BindingSpecificationDto> $bindings keyed by binding id, each dto already canonicalized
     *                                                         ({@see BindingSpecificationCanonicalizer}); the semantic constraint assumes
     *                                                         canonical `{loader, config}` resolves entries and skips sugared (non-array) ones, leaving only the
     *                                                         shape constraint to reject them
     * @param array<string, ContentSystemElementTypeSpecification> $typeOverlay type-name → spec, resolved before the
     *                                                                          registry (an app's own types at install/validate time); empty for every non-app path
     */
    public function __construct(
        #[Assert\Valid]
        public array $bindings,
        public array $typeOverlay = [],
    ) {
    }
}
