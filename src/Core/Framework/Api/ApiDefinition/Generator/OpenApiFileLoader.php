<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 *
 * @phpstan-import-type OpenApiSpec from DefinitionService
 */
#[Package('framework')]
class OpenApiFileLoader
{
    /**
     * @param string[] $paths
     */
    public function __construct(private readonly array $paths)
    {
    }

    /**
     * @return OpenApiSpec
     */
    public function loadOpenapiSpecification(): array
    {
        $spec = [
            'paths' => [],
            'components' => [],
            'tags' => [],
        ];

        if ($this->paths === []) {
            return $spec;
        }

        $finder = new Finder();
        $finder->in($this->paths)->name('*.json');

        foreach ($finder as $entry) {
            try {
                $data = json_decode((string) file_get_contents($entry->getPathname()), true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException $exception) {
                throw ApiException::invalidSchemaDefinitions($entry->getPathname(), $exception);
            }

            $spec['paths'] = \array_replace_recursive($spec['paths'], $data['paths'] ?? []);
            $spec['components'] = array_merge_recursive(
                $spec['components'],
                $data['components'] ?? []
            );
            $spec['tags'] = array_merge_recursive(
                $spec['tags'],
                $data['tags'] ?? []
            );
        }

        $spec['components'] = $this->deduplicateArraysRecursive($spec['components']);
        $spec['tags'] = $this->deduplicateArraysRecursive($spec['tags']);

        return $spec;
    }

    /**
     * Recursively deduplicates sequential arrays of scalars produced by array_merge_recursive.
     * Only applies to flat lists (e.g. enum values, required fields) — arrays containing
     * nested objects are left untouched to avoid "Array to string conversion" issues.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    private function deduplicateArraysRecursive(array $data): array
    {
        foreach ($data as $key => $value) {
            if (!\is_array($value)) {
                continue;
            }

            if (!array_is_list($value)) {
                $data[$key] = $this->deduplicateArraysRecursive($value);

                continue;
            }

            $allScalar = true;
            foreach ($value as $item) {
                if (\is_array($item)) {
                    $allScalar = false;

                    break;
                }
            }

            if ($allScalar) {
                $data[$key] = array_values(array_unique($value));
            }
        }

        return $data;
    }
}
