<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentSystemDataLoaderSchemaGenerator
{
    public function __construct(
        private readonly AbstractContentSystemDataLoaderMapResolver $resolver,
    ) {
    }

    /**
     * @return array{sources: array<string, array{
     *     configKeys: list<array{name: string, kind: string, type: string, required: bool, default?: mixed, adminUI?: array<string, mixed>}>,
     *     types: list<array{producedType: string, configTemplate: array<string, mixed>, genericParameters: list<string>}>
     * }>}
     */
    public function getSchema(): array
    {
        $map = $this->resolver->resolve();

        $sources = [];
        foreach ($map->sourceToCapabilities as $source => $capabilities) {
            $types = [];
            foreach ($capabilities as $capability) {
                $types[] = [
                    'producedType' => $capability->producedType,
                    'configTemplate' => $capability->configTemplate,
                    'genericParameters' => $capability->genericParameters,
                ];
            }

            $sources[$source] = [
                'configKeys' => $this->configKeysSchema($map->configSpecificationFor($source)),
                'types' => $types,
            ];
        }

        return ['sources' => $sources];
    }

    /**
     * @return list<array{name: string, kind: string, type: string, required: bool, default?: mixed, adminUI?: array<string, mixed>}>
     */
    private function configKeysSchema(LoaderConfigSpecification $specification): array
    {
        $configKeys = [];
        foreach ($specification->keys as $key) {
            $configKey = [
                'name' => $key->name,
                'kind' => $key->kind->value,
                'type' => $key->type,
                'required' => $key->required,
            ];

            if ($key->hasDefault) {
                $configKey['default'] = $key->default;
            }

            if ($key->adminUI !== null) {
                $configKey['adminUI'] = $key->adminUI;
            }

            $configKeys[] = $configKey;
        }

        return $configKeys;
    }
}
