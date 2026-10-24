<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace Shopware\Core\Example\FieldPresence;

use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Field presence
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class CheckNumberFieldPresenceResponse extends AbstractResponse
{
    public const DEFAULT_REQUIRED_SCALAR_WITH_DEFAULT = 2.5;

    public const DEFAULT_REQUIRED_NULLABLE_WITH_DEFAULT = 2.5;

    public const DEFAULT_OPTIONAL_SCALAR_WITH_DEFAULT = 2.5;

    public const DEFAULT_OPTIONAL_NULLABLE_WITH_DEFAULT = 2.5;

    public const DEFAULT_REQUIRED_NULLABLE_WITH_NULL_DEFAULT = null;

    public const DEFAULT_OPTIONAL_NULLABLE_WITH_NULL_DEFAULT = null;

    public float $optionalScalar;

    public float $optionalScalarWithDefault;

    public ?float $optionalNullable;

    public ?float $optionalNullableWithDefault;

    public ?float $optionalNullableWithNullDefault;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        public float $requiredScalar,
        #[Assert\NotNull]
        public float $requiredScalarWithDefault,
        public ?float $requiredNullable,
        public ?float $requiredNullableWithDefault,
        public ?float $requiredNullableWithNullDefault,
    ) {
        parent::__construct();
    }
}
