<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\Log\Package;

/**
 * An operation under OpenAPI 3.2 additionalOperations, whose method is its map key.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Api\OpenApi\OpenApiDtoGeneratorTest
 */
#[Package('framework')]
final class OpenApiDtoOperation extends OA\Operation
{
}
