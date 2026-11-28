<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use OpenApi\Annotations as OA;
use OpenApi\Undefined;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Framework\Api\OpenApi\OpenApiDtoGeneratorTest
 */
#[Package('framework')]
final class OpenApiDtoComponents extends OA\Components
{
    /**
     * @var array<string, OA\MediaType>|string
     */
    public array|string $mediaTypes = Undefined::UNDEFINED;
}
