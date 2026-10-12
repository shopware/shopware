<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Api\Validation;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraint;

/**
 * The one statement of the "at least one entry in `values` or `removeKeys`" invariant, attached to the
 * draft and the persisted update-element-properties request DTOs so the two routes cannot drift apart in
 * rule or wording.
 *
 * @internal only for use by the content-system mutation request DTOs
 */
#[Package('framework')]
#[\Attribute(\Attribute::TARGET_CLASS)]
final class UpdateElementPropertiesNotEmpty extends Constraint
{
    final public const EMPTY_REQUEST_ERROR = 'c4f4e6a7-2f1e-4d38-9e3a-5b7f0c2d8a41';

    protected const ERROR_NAMES = [
        self::EMPTY_REQUEST_ERROR => 'EMPTY_REQUEST_ERROR',
    ];

    public string $message = 'An update-element-properties request must carry at least one entry in "values" or "removeKeys" (updateElementPropertiesEmpty).';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
