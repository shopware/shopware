<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentSystemDataLoaderTypeDescriptor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @final
 */
#[Package('framework')]
class ContentSystemDataLoaderTypeResolver extends AbstractContentSystemDataLoaderTypeResolver
{
    /**
     * @param array<string, list<array{className: class-string<Struct>, genericParameters: list<class-string<Struct>>}>> $compiledSourceToTypes
     */
    public function __construct(
        private readonly array $compiledSourceToTypes,
        private readonly EventDispatcherInterface $dispatcher,
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

            $event = new ContentSystemDataLoaderTypesResolvedEvent($source, $types);
            $this->dispatcher->dispatch($event, ContentSystemDataLoaderTypesResolvedEvent::class . '.' . $source);

            $sourceToTypes[$source] = $event->types;
        }

        return new ContentSystemDataLoaderTypeMap($sourceToTypes);
    }
}
