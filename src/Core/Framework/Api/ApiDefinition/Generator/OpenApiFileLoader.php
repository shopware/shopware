<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
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
            'components' => [
                'schemas' => [],
            ],
        ];

        if (empty($this->paths)) {
            return $spec;
        }

        $finder = new Finder();
        $finder->in($this->paths)->name('*.json');

        foreach ($finder as $entry) {
            $data = json_decode((string) file_get_contents($entry->getPathname()), true, \JSON_THROW_ON_ERROR, \JSON_THROW_ON_ERROR);

            $spec['paths'] = \array_replace_recursive($spec['paths'], $data['paths'] ?? []);
            $spec['components']['schemas'] = array_merge(
                $spec['components']['schemas'],
                $data['components']['schemas'] ?? []
            );
        }

        return $spec;
    }
}
