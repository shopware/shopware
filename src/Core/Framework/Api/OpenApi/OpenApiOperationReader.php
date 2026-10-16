<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
final class OpenApiOperationReader
{
    /**
     * @var list<string>
     */
    private const STANDARD_METHODS = [
        Request::METHOD_GET,
        Request::METHOD_POST,
        Request::METHOD_PUT,
        Request::METHOD_PATCH,
        Request::METHOD_DELETE,
        Request::METHOD_OPTIONS,
        Request::METHOD_HEAD,
        Request::METHOD_TRACE,
        Request::METHOD_QUERY,
    ];

    /**
     * @param array<string, mixed> $pathItem
     *
     * @return iterable<array{method: string, location: array{0: string, 1?: string}, operation: array<string, mixed>}>
     */
    public function read(array $pathItem): iterable
    {
        foreach (self::STANDARD_METHODS as $method) {
            $field = strtolower($method);
            $operation = $pathItem[$field] ?? null;

            if (\is_array($operation)) {
                yield [
                    'method' => $method,
                    'location' => [$field],
                    'operation' => $operation,
                ];
            }
        }

        $additionalOperations = $pathItem['additionalOperations'] ?? [];
        if (!\is_array($additionalOperations)) {
            return;
        }

        foreach ($additionalOperations as $method => $operation) {
            if (\is_string($method) && \is_array($operation)) {
                yield [
                    'method' => $method,
                    'location' => ['additionalOperations', $method],
                    'operation' => $operation,
                ];
            }
        }
    }
}
