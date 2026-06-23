<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextConsumer;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextDefinitions;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\ContextProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Context\Distribution\BroadcastDistributionConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Slot\SlotContent;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\SlotSpecification;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\ReplaceElement;

/**
 * @internal
 */
#[CoversClass(ReplaceElement::class)]
class ReplaceElementTest extends TestCase
{
    use AssertsImmutableInput;

    #[TestDox('keeps the element id while swapping the component')]
    public function testReplaceKeepsElementId(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old')];

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New'))->apply($tree);

        static::assertSame('el', $result[0]->getId());
        static::assertSame('Sw:New', $result[0]->getComponent());
    }

    /**
     * @param array<string, mixed> $oldProperties
     * @param array<string, mixed> $expectedKept
     */
    #[DataProvider('carriesOverPropertiesProvider')]
    #[TestDox('carries over only primitive properties whose key and type match the new type')]
    public function testReplacePropertyCarryover(array $oldProperties, array $expectedKept): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], $oldProperties)];

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New'))->apply($tree);

        static::assertSame($expectedKept, $result[0]->getProperties());
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, mixed>}>
     */
    public static function carriesOverPropertiesProvider(): iterable
    {
        yield 'matching string kept' => [['headline' => 'Hi'], ['headline' => 'Hi']];
        yield 'matching integer kept' => [['count' => 5], ['count' => 5]];
        yield 'matching number keeps an int' => [['ratio' => 5], ['ratio' => 5]];
        yield 'matching number keeps a float' => [['ratio' => 1.5], ['ratio' => 1.5]];
        yield 'matching boolean kept' => [['featured' => true], ['featured' => true]];
        yield 'mismatched type dropped' => [['count' => 'text'], []];
        yield 'float dropped from an integer property' => [['count' => 1.5], []];
        yield 'key absent from new type dropped' => [['ghost' => 'x'], []];
        yield 'scalar under a reference key dropped' => [['product' => 'oops'], []];
    }

    #[TestDox('reports static property values the new type cannot hold via droppedProperties')]
    public function testReplaceReportsDroppedProperties(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], [
            'headline' => 'Hi',
            'ghost' => 'orphaned-value',
            'count' => 'not-an-int',
        ])];

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New');
        $result = $replace->apply($tree);

        static::assertSame(['headline' => 'Hi'], $result[0]->getProperties());
        static::assertSame(['ghost' => 'orphaned-value', 'count' => 'not-an-int'], $replace->droppedProperties());
    }

    #[TestDox('resets droppedProperties on re-apply so a second run does not accumulate the first run drops')]
    public function testReplaceResetsDroppedPropertiesOnReapply(): void
    {
        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New');

        $replace->apply([new ContentElement('el', 'Sw:Old', [], ['ghost' => 'first-run'])]);
        $replace->apply([new ContentElement('el', 'Sw:Old', [], ['count' => 'second-run'])]);

        static::assertSame(['count' => 'second-run'], $replace->droppedProperties());
    }

    #[TestDox('keeps wiring whose key matches a new-type reference property and does not report it as dropped')]
    public function testReplaceKeepsMatchingWiring(): void
    {
        $requirement = new DataRequirement('product', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $tree = [new ContentElement('el', 'Sw:Old', ['product' => $requirement])];

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New');
        $result = $replace->apply($tree);

        static::assertSame(['product' => $requirement], $result[0]->getDataRequirements());
        static::assertSame([], $replace->droppedWiring());
    }

    #[TestDox('drops wiring whose key is absent from the new type and reports it without re-mapping')]
    public function testReplaceDropsAndReportsAbsentWiring(): void
    {
        $requirement = new DataRequirement('legacy', 'entity', static::createStub(AbstractContentDataLoaderConfig::class));
        $tree = [new ContentElement('el', 'Sw:Old', ['legacy' => $requirement])];

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New');
        $result = $replace->apply($tree);

        static::assertSame([], $result[0]->getDataRequirements());
        static::assertSame(['legacy'], $replace->droppedWiring());
    }

    #[TestDox('reports a dropped context provider and consumer key once each')]
    public function testReplaceReportsDroppedContextWiring(): void
    {
        $definitions = new ContextDefinitions(
            ['legacyProvider' => new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple())],
            ['legacyConsumer' => new ContextConsumer(ContextType::Single, true)],
        );
        $tree = [new ContentElement('el', 'Sw:Old', [], [], [], $definitions)];

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New');
        $replace->apply($tree);

        static::assertSame(['legacyProvider', 'legacyConsumer'], $replace->droppedWiring());
    }

    #[TestDox('keeps context definitions whose key matches a new-type reference property and drops the rest')]
    public function testReplaceContextDefinitionsCarryover(): void
    {
        $kept = new ContextConsumer(ContextType::Single, true);
        $dropped = new ContextProvider(ContextType::Single, BroadcastDistributionConfig::simple());
        $definitions = new ContextDefinitions(['legacy' => $dropped], ['product' => $kept]);
        $tree = [new ContentElement('el', 'Sw:Old', [], [], [], $definitions)];

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New'))->apply($tree);

        static::assertSame(['product' => $kept], $result[0]->getAcceptsContext());
        static::assertSame([], $result[0]->getProvidesContext());
    }

    #[TestDox('keeps the children of a slot that exists in the new type')]
    public function testReplaceKeepsKnownSlot(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], [], [
            'content' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
        ])];

        $result = (new ReplaceElement($this->registry(), 'el', 'Sw:New'))->apply($tree);

        $children = array_values($result[0]->getSlots()['content']->getElements());
        static::assertSame('child', $children[0]->getId());
    }

    #[TestDox('detaches children of a slot absent from the new type into orphaned without re-mapping')]
    public function testReplaceOrphansAbsentSlotChildren(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], [], [
            'legacy' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
        ])];

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New');
        $result = $replace->apply($tree);

        static::assertFalse($result[0]->hasSlots());
        static::assertSame(['child'], array_map(static fn (ContentElement $e): string => $e->getId(), $replace->orphaned()));
    }

    #[TestDox('reports the replaced element and its kept descendants as affected')]
    public function testReplaceAffectedCoversKeptSubtree(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], [], [
            'content' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
        ])];

        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:New');
        $replace->apply($tree);

        static::assertSame(['el', 'child'], $replace->affected());
    }

    #[TestDox('does not mutate the input tree')]
    public function testReplaceDoesNotMutateInput(): void
    {
        $tree = [new ContentElement('el', 'Sw:Old', [], ['headline' => 'Hi'], [
            'content' => new SlotContent([new ContentElement('child', 'Sw:Block')]),
        ])];
        $before = $this->snapshotTree($tree);

        (new ReplaceElement($this->registry(), 'el', 'Sw:New'))->apply($tree);

        $this->assertInputTreeUnmutated($before, $tree);
    }

    #[TestDox('rejects an unregistered new type with a 400')]
    public function testReplaceUnknownNewTypeRejected(): void
    {
        $replace = new ReplaceElement($this->registry(), 'el', 'Sw:Ghost');

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Sw:Ghost'));
        $replace->apply([new ContentElement('el', 'Sw:Old')]);
    }

    #[TestDox('rejects replacing an element absent from the tree with a 400')]
    public function testReplaceMissingElementRejected(): void
    {
        $replace = new ReplaceElement($this->registry(), 'ghost', 'Sw:New');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $replace->apply([new ContentElement('el', 'Sw:Old')]);
    }

    private function registry(): AbstractContentSystemElementTypeRegistry
    {
        $spec = new ContentSystemElementTypeSpecification(
            'Sw:New',
            'New',
            '',
            null,
            null,
            new CopilotSpecification('', []),
            [
                'headline' => $this->primitive('string'),
                'count' => $this->primitive('integer'),
                'ratio' => $this->primitive('number'),
                'featured' => $this->primitive('boolean'),
                'product' => $this->reference(),
            ],
            [new SlotSpecification('content', null, [], '')],
        );
        $specs = ['Sw:New' => $spec];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => isset($specs[$name]));
        $registry->method('get')->willReturnCallback(static fn (string $name): ContentSystemElementTypeSpecification => $specs[$name]);

        return $registry;
    }

    private function primitive(string $type): PropertySpecification
    {
        return new PropertySpecification('prop', new PropertyType($type, false, null, null), false, '', '', null);
    }

    private function reference(): PropertySpecification
    {
        return new PropertySpecification('prop', new PropertyType('SomeEntity', false, null, null), false, '', '', null);
    }
}
