<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataContext;

use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Resolves nested property paths on Struct objects for context distribution.
 *
 * @internal
 *
 * @final
 */
#[Package('discovery')]
class ContextPathResolver
{
    /**
     * @return array<string>
     */
    public function parseContextKey(string $key): array
    {
        $segments = explode('.', $key);
        array_shift($segments);

        return $segments;
    }

    /**
     * @param array<string> $path
     *
     * @throws ContentSystemException If path cannot be resolved and $required is true
     */
    public function resolvePath(?Struct $data, array $path, bool $required, string $fullPath, string $elementId): mixed
    {
        if ($path === []) {
            return $data;
        }

        if ($data === null) {
            if ($required) {
                throw ContentSystemException::contextPathNotResolvable(
                    $fullPath,
                    $elementId,
                    'Base context data is null'
                );
            }

            return null;
        }

        $current = $data;
        $pathCount = \count($path);

        foreach ($path as $index => $segment) {
            if (!$current instanceof Struct) {
                if ($required) {
                    $traversedPath = implode('.', \array_slice($path, 0, $index));
                    throw ContentSystemException::contextPathNotResolvable(
                        $fullPath,
                        $elementId,
                        "Intermediate value at '{$traversedPath}' is not a Struct instance"
                    );
                }

                return null;
            }

            $vars = $current->getVars();

            if (!\array_key_exists($segment, $vars)) {
                if ($required) {
                    $traversedPath = implode('.', \array_slice($path, 0, $index + 1));
                    throw ContentSystemException::contextPathNotResolvable(
                        $fullPath,
                        $elementId,
                        "Property '{$segment}' does not exist at path '{$traversedPath}'"
                    );
                }

                return null;
            }

            $current = $vars[$segment];

            if ($current === null && $index < $pathCount - 1) {
                if ($required) {
                    $traversedPath = implode('.', \array_slice($path, 0, $index + 1));
                    throw ContentSystemException::contextPathNotResolvable(
                        $fullPath,
                        $elementId,
                        "Intermediate value at '{$traversedPath}' is null"
                    );
                }

                return null;
            }
        }

        return $current;
    }

    /**
     * Check if a consumer key matches or is a subpath of a provider key.
     *
     * Enables consumers to access nested properties via path resolution
     * (provider 'product' satisfies consumer 'product.manufacturer.name').
     */
    public function matches(string $providerKey, string $consumerKey): bool
    {
        if ($providerKey === $consumerKey) {
            return true;
        }

        return str_starts_with($consumerKey, $providerKey . '.');
    }

    public function extractBaseKey(string $key): string
    {
        return explode('.', $key)[0];
    }
}
