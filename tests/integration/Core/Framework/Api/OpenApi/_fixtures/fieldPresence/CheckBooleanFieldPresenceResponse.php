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
final class CheckBooleanFieldPresenceResponse extends AbstractResponse
{
    public const DEFAULT_REQUIRED_SCALAR_WITH_DEFAULT = false;

    public const DEFAULT_REQUIRED_NULLABLE_WITH_DEFAULT = false;

    public const DEFAULT_OPTIONAL_SCALAR_WITH_DEFAULT = false;

    public const DEFAULT_OPTIONAL_NULLABLE_WITH_DEFAULT = false;

    public const DEFAULT_REQUIRED_NULLABLE_WITH_NULL_DEFAULT = null;

    public const DEFAULT_OPTIONAL_NULLABLE_WITH_NULL_DEFAULT = null;

    public bool $optionalScalar;

    public bool $optionalScalarWithDefault;

    public ?bool $optionalNullable;

    public ?bool $optionalNullableWithDefault;

    public ?bool $optionalNullableWithNullDefault;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        public bool $requiredScalar,
        #[Assert\NotNull]
        public bool $requiredScalarWithDefault,
        public ?bool $requiredNullable,
        public ?bool $requiredNullableWithDefault,
        public ?bool $requiredNullableWithNullDefault,
    ) {
        parent::__construct();
    }
}
