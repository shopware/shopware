<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\UnmapProperty;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(UnmapProperty::class)]
class UnmapPropertyTest extends TestCase
{
    public function testRemovesOnlyTheSelectedMappingAndKeepsItsAuthoredFallback(): void
    {
        $mapped = new ContextConsumer(
            ContextType::Single,
            required: false,
            scope: ConsumerScope::Root,
            source: MappingSourceReference::fromRootPath('product.name'),
        );
        $other = new ContextConsumer(ContextType::Single, required: false);
        $element = new StoredElement(
            'element-1',
            'Sw:Text',
            properties: [
                'text' => StoredValue::ofString('Text fallback'),
                'headline' => StoredValue::ofString('Headline fallback'),
            ],
            contextDefinitions: new ContextDefinitions(consumers: ['text' => $mapped, 'headline' => $other]),
        );
        $tree = new StoredTree([$element]);

        $mutation = new UnmapProperty('element-1', 'text');
        $unmapped = $mutation->apply($tree);

        $consumers = $unmapped->roots[0]->contextDefinitions->getAllConsumers();
        static::assertArrayNotHasKey('text', $consumers);
        static::assertSame($other, $consumers['headline']);
        static::assertSame('Text fallback', $unmapped->roots[0]->property('text')?->asString());
        static::assertSame(['element-1'], $mutation->affected());
        static::assertSame('product.name', $element->contextDefinitions->getAllConsumers()['text']->source?->displayName());
    }

    public function testLeavesTreeUntouchedWhenThePropertyIsNotMapped(): void
    {
        $consumer = new ContextConsumer(ContextType::Single, required: false);
        $element = new StoredElement(
            'element-1',
            'Sw:Text',
            contextDefinitions: new ContextDefinitions(consumers: ['text' => $consumer]),
        );
        $tree = new StoredTree([$element]);

        static::assertSame($tree, (new UnmapProperty('element-1', 'text'))->apply($tree));
    }

    public function testThrowsWhenTheTargetElementDoesNotExist(): void
    {
        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('missing'));

        (new UnmapProperty('missing', 'text'))->apply(new StoredTree([]));
    }
}
