<?php declare(strict_types=1);

/**
 * This file is auto-generated.
 * Do not edit manually.
 *
 * Last generated: 2026-10-28 00:00:00
 */

namespace Shopware\Tests\Integration\Core\Framework\Api\OpenApi\Fixture;

use Shopware\Core\Framework\Api\Response\AbstractResponse;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class TransformFieldsResponse extends AbstractResponse
{
    public const DEFAULT_REQUIRED_SCALAR_WITH_DEFAULT = 'schema-default';

    public const DEFAULT_REQUIRED_NULLABLE_WITH_DEFAULT = 'schema-default';

    public const DEFAULT_OPTIONAL_SCALAR_WITH_DEFAULT = 'schema-default';

    public const DEFAULT_OPTIONAL_NULLABLE_WITH_DEFAULT = null;

    public string $optionalScalar;

    public string $optionalScalarWithDefault;

    public ?string $optionalNullable;

    public ?string $optionalNullableWithDefault;

    #[Assert\Valid]
    public FieldDetails $details;

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
    ) {
        parent::__construct();
    }
}
