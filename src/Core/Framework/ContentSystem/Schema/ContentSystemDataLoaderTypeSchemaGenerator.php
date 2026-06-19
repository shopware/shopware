<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentSystemDataLoaderTypeSchemaGenerator
{
    public function __construct(
        private readonly AbstractContentSystemDataLoaderTypeResolver $resolver,
    ) {
    }

    /**
     * @return array{sources: array<string, array{types: list<array{producedType: string, configTemplate: array<string, mixed>, requiredConfigKeys: list<string>, genericParameters: list<string>}>}>}
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
                    'requiredConfigKeys' => $capability->requiredConfigKeys,
                    'genericParameters' => $capability->genericParameters,
                ];
            }

            $sources[$source] = ['types' => $types];
        }

        return ['sources' => $sources];
    }
}
