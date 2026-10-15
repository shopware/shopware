<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace Shopware\Core\Example\FieldPresence;

use Shopware\Core\Framework\Api\Request\AbstractRequest;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class CheckNumberFieldPresenceRequest extends AbstractRequest
{
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
    }
}
