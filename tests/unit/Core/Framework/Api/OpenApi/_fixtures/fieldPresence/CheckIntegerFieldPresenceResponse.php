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
final class CheckIntegerFieldPresenceResponse extends AbstractResponse
{
    public const DEFAULT_REQUIRED_SCALAR_WITH_DEFAULT = 0;

    public const DEFAULT_REQUIRED_NULLABLE_WITH_DEFAULT = 0;

    public const DEFAULT_OPTIONAL_SCALAR_WITH_DEFAULT = 0;

    public const DEFAULT_OPTIONAL_NULLABLE_WITH_DEFAULT = 0;

    public const DEFAULT_REQUIRED_NULLABLE_WITH_NULL_DEFAULT = null;

    public const DEFAULT_OPTIONAL_NULLABLE_WITH_NULL_DEFAULT = null;

    public int $optionalScalar;

    public int $optionalScalarWithDefault;

    public ?int $optionalNullable;

    public ?int $optionalNullableWithDefault;

    public ?int $optionalNullableWithNullDefault;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        public int $requiredScalar,
        #[Assert\NotNull]
        public int $requiredScalarWithDefault,
        public ?int $requiredNullable,
        public ?int $requiredNullableWithDefault,
        public ?int $requiredNullableWithNullDefault,
    ) {
        parent::__construct();
    }
}
