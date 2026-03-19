<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentSystemDataLoaderTypeDescriptor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentSystemDataLoaderTypeResolver extends AbstractContentSystemDataLoaderTypeResolver
{
    /**
     * @param ServiceLocator<AbstractContentDataLoader<Struct>> $loaders
     * @param array<string, list<array{className: class-string<Struct>, genericParameters: list<class-string<Struct>>}>> $compiledSourceToTypes
     */
    public function __construct(
        private readonly ServiceLocator $loaders,
        private readonly array $compiledSourceToTypes,
    ) {
    }

    public function resolve(): ContentSystemDataLoaderTypeMap
    {
        $sourceToTypes = [];

        foreach ($this->compiledSourceToTypes as $source => $entries) {
            $types = [];
            foreach ($entries as $entry) {
                $types[] = new ContentSystemDataLoaderTypeDescriptor(
                    $entry['className'],
                    $entry['genericParameters'],
                );
            }

            if ($this->loaders->has($source)) {
                $this->loaders->get($source)->overrideProvidedTypes($types);
            }

            $sourceToTypes[$source] = $types;
        }

        return new ContentSystemDataLoaderTypeMap($sourceToTypes);
    }
}
