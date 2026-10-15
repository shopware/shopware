<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-07-07 00:00:00
 */

namespace Shopware\Core\Example\FieldPresence;

use Shopware\Core\Framework\Api\AbstractDto;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class SharedFieldPresence extends AbstractDto
{
    public string $optionalScalar;

    public string $optionalScalarWithDefault;

    public ?string $optionalNullable;

    public ?string $optionalNullableWithDefault;

    public ?string $optionalNullableWithNullDefault;

    /**
     * @internal
     */
    public function __construct(
        #[Assert\NotNull]
        public string $requiredScalar,
        #[Assert\NotNull]
        public string $requiredScalarWithDefault,
        public ?string $requiredNullable,
        public ?string $requiredNullableWithDefault,
        public ?string $requiredNullableWithNullDefault,
    ) {
    }
}
