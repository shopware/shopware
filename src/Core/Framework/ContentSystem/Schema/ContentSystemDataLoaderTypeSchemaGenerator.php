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
     * @return array{sources: array<string, array{types: list<array{className: string, genericParameters: list<string>}>}>}
     */
    public function getSchema(): array
    {
        $map = $this->resolver->resolve();

        $sources = [];
        foreach ($map->sourceToTypes as $source => $descriptors) {
            $types = [];
            foreach ($descriptors as $descriptor) {
                $types[] = [
                    'className' => $descriptor->className,
                    'genericParameters' => $descriptor->genericParameters,
                ];
            }

            $sources[$source] = ['types' => $types];
        }

        return ['sources' => $sources];
    }
}
