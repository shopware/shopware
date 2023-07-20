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
#[Package('core')]
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

        if (empty($this->paths)) {
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

        return $spec;
    }
}
