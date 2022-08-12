<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\ApiDefinition\Generator;

use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Symfony\Component\Finder\Finder;

/**
 * @internal
 * @phpstan-import-type OpenApiSpec from DefinitionService
 */
class OpenApiFileLoader
{
    /**
     * @var string[]
     */
    private array $paths;

    /**
     * @param string[] $paths
     */
    public function __construct(array $paths)
    {
        $this->paths = $paths;
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
            $data = json_decode((string) file_get_contents($entry->getPathname()), true, \JSON_THROW_ON_ERROR);

            $spec['paths'] = array_merge($spec['paths'], $data['paths'] ?? []);
            $spec['components']['schemas'] = array_merge(
                $spec['components']['schemas'],
                $data['components']['schemas'] ?? []
            );
        }

        return $spec;
    }
}
