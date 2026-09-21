<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ConsumerScope;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopware\Core\Framework\Log\Package;

/**
 * The write gate and the diagnostics layer both ask whether a root-scoped consumer is an author's mapping or
 * the wiring `Mutation/ContextConsumerMirror` mirrored, and a disagreement between them is silently wrong in
 * both directions: a mapping the gate skips goes unvalidated, and wiring diagnostics reads as a mapping stops
 * demanding the loader input it genuinely needs. These pin the shared answer.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(MappingConsumers::class)]
class MappingConsumersTest extends TestCase
{
    #[TestDox('reads a dotted root-scoped aliased consumer as a mapping')]
    public function testADottedRootScopedAliasedConsumerIsAMapping(): void
    {
        static::assertTrue((new MappingConsumers())->isMapping($this->rootConsumer('text'), 'category.name'));
    }

    #[TestDox('does not read the undotted consumer the mutation layer mirrors for resolved wiring as a mapping')]
    public function testAnUndottedConsumerIsMirroredWiringRatherThanAMapping(): void
    {
        static::assertFalse((new MappingConsumers())->isMapping($this->rootConsumer('listing'), 'productListing'));
    }

    #[TestDox('does not read a consumer with no property alias as a mapping')]
    public function testAConsumerWithoutAPropertyAliasIsNotAMapping(): void
    {
        $consumer = new ContextConsumer(type: ContextType::Single, required: false, scope: ConsumerScope::Root);

        static::assertFalse((new MappingConsumers())->isMapping($consumer, 'category.name'));
    }

    #[TestDox('does not read a parent-scoped consumer as a mapping')]
    public function testAParentScopedConsumerIsNotAMapping(): void
    {
        $consumer = new ContextConsumer(
            type: ContextType::Single,
            required: false,
            propertyAlias: 'text',
            scope: ConsumerScope::Parent,
        );

        static::assertFalse((new MappingConsumers())->isMapping($consumer, 'product.name'));
    }

    #[TestDox('collects only the property keys a mapping fills, ignoring mirrored wiring onto another property')]
    public function testCollectsTheMappedPropertyKeysAlone(): void
    {
        $element = new StoredElement(
            'el-1',
            'Sw:Media:Image',
            [],
            [],
            [],
            new ContextDefinitions([], [
                'category.media' => $this->rootConsumer('media'),
                'productListing' => $this->rootConsumer('listing'),
            ]),
        );

        static::assertSame(['media' => true], (new MappingConsumers())->mappedPropertyKeys($element));
    }

    private function rootConsumer(string $propertyAlias): ContextConsumer
    {
        return new ContextConsumer(
            type: ContextType::Single,
            required: false,
            propertyAlias: $propertyAlias,
            scope: ConsumerScope::Root,
        );
    }
}
