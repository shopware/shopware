<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Diagnostics\LayoutDiagnostics;
use Shopware\Core\Framework\ContentSystem\Validation\StoredMappingValidator;
use Shopware\Core\Framework\Log\Package;

/**
 * One inadmissible stored mapping, in the form both reporting surfaces need and neither one's own.
 *
 * {@see StoredMappingValidator} turns it into a write-path `ConstraintViolation` keyed on the property, and
 * {@see LayoutDiagnostics} into a `Violation` the diagnose and draft mutation routes echo in their 200 body.
 * The two exist because the surfaces answer different questions — a write must refuse, an editor must
 * explain — and keeping the RULE in one place ({@see StoredMappingInspector}) is what stops them drifting
 * into disagreeing about which mappings are legal.
 *
 * @internal
 */
#[Package('framework')]
final readonly class MappingProblem
{
    public function __construct(
        public string $elementId,
        /**
         * The declared property the mapping fills. Both surfaces key on this rather than on the consumer
         * key, because it names the control the author acted on.
         */
        public string $propertyKey,
        /**
         * The mapped path, e.g. `category.name`. Carried separately because it is the value the author
         * chose and the one a message has to name to be actionable.
         */
        public string $consumerKey,
        public ContentSystemException $exception,
    ) {
    }
}
