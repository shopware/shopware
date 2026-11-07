<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use OpenApi\Annotations as OA;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final class OpenApiOperationReader
{
    /**
     * @return iterable<array{method: string, location: array{0: string, 1?: string}, operation: OA\Operation}>
     */
    public function read(OA\PathItem $pathItem): iterable
    {
        foreach ($pathItem->operations() as $operation) {
            $method = strtolower($operation->method);
            yield ['method' => strtoupper($method), 'location' => [$method], 'operation' => $operation];
        }
        if ($pathItem instanceof OpenApiDtoPathItem && \is_array($pathItem->additionalOperations)) {
            foreach ($pathItem->additionalOperations as $method => $operation) {
                yield ['method' => $method, 'location' => ['additionalOperations', $method], 'operation' => $operation];
            }
        }
    }
}
