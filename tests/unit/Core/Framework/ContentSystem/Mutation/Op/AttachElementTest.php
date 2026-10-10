<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\RootSourceConfigMap;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\AttachElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AttachElement::class)]
class AttachElementTest extends TestCase
{
    #[TestDox('appends the supplied subtree at the root with a server-minted id')]
    public function testAttachesAtRootWithMintedId(): void
    {
        $tree = new StoredTree([new StoredElement('existing', 'Sw:Block')]);

        $result = $this->attach(new StoredElement('incoming', 'Sw:Card'))->apply($tree);

        static::assertCount(2, $result->roots);
        static::assertSame('existing', $result->roots[0]->id);
        static::assertSame('Sw:Card', $result->roots[1]->component);
        static::assertNotSame('incoming', $result->roots[1]->id);
        static::assertTrue(Uuid::isValid($result->roots[1]->id));
    }

    #[TestDox('remints every id in the supplied subtree, never trusting client ids')]
    public function testRemintsEverySubtreeId(): void
    {
        $incoming = new StoredElement('incoming', 'Sw:Block', [], [], [
            'content' => [new StoredElement('incoming-child', 'Sw:Card')],
        ]);

        $result = $this->attach($incoming)->apply(new StoredTree([]));

        $attached = $result->roots[0];
        $child = $attached->slots['content'][0];
        static::assertNotSame('incoming', $attached->id);
        static::assertNotSame('incoming-child', $child->id);
        static::assertSame('Sw:Card', $child->component);
    }

    #[TestDox('reports every reminted subtree id as affected')]
    public function testAffectedAreMintedSubtreeIds(): void
    {
        $incoming = new StoredElement('incoming', 'Sw:Block', [], [], [
            'content' => [new StoredElement('incoming-child', 'Sw:Card')],
        ]);

        $attach = $this->attach($incoming);
        $result = $attach->apply(new StoredTree([]));

        $attached = $result->roots[0];
        static::assertSame([$attached->id, $attached->slots['content'][0]->id], $attach->affected());
    }

    #[TestDox('reports every reminted subtree id as created, because the whole splice is new to the layout')]
    public function testCreatedAreEveryMintedSubtreeId(): void
    {
        $incoming = new StoredElement('incoming', 'Sw:Block', [], [], [
            'content' => [new StoredElement('incoming-child', 'Sw:Card'), new StoredElement('incoming-sibling', 'Sw:Card')],
        ]);

        $attach = $this->attach($incoming);
        $result = $attach->apply(new StoredTree([]));

        $attached = $result->roots[0];
        static::assertSame(
            [$attached->id, $attached->slots['content'][0]->id, $attached->slots['content'][1]->id],
            $attach->created(),
        );
    }

    #[TestDox('attaches the subtree into a parent slot at an explicit index')]
    public function testAttachesIntoParentSlotAtIndex(): void
    {
        $tree = new StoredTree([new StoredElement('parent', 'Sw:Block', [], [], [
            'content' => [new StoredElement('first', 'Sw:Card')],
        ])]);

        $result = $this->attach(new StoredElement('incoming', 'Sw:Card'), 'parent', 'content', 0)->apply($tree);

        $children = $result->roots[0]->slots['content'];
        static::assertCount(2, $children);
        static::assertNotSame('incoming', $children[0]->id);
        static::assertSame('first', $children[1]->id);
    }

    #[TestDox('clamps an out-of-range index, appending the supplied subtree to the end of the target list')]
    public function testAttachClampsOutOfRangeIndex(): void
    {
        $tree = new StoredTree([new StoredElement('block-a', 'Sw:Card'), new StoredElement('block-b', 'Sw:Card')]);

        $result = $this->attach(new StoredElement('incoming', 'Sw:Card'), null, null, 99)->apply($tree);

        static::assertCount(3, $result->roots);
        static::assertSame(['block-a', 'block-b'], [$result->roots[0]->id, $result->roots[1]->id]);
        static::assertNotSame('incoming', $result->roots[2]->id);
    }

    #[TestDox('keeps a language-map property value on the reminted subtree unchanged')]
    public function testAttachKeepsLanguageMapUnchanged(): void
    {
        $german = Uuid::randomHex();
        $translations = [Defaults::LANGUAGE_SYSTEM => 'Autumn sale', $german => 'Herbstschlussverkauf'];
        $incoming = StoredElementBuilder::create('Sw:Card', 'incoming')->withProperty('text', $translations)->build();

        $result = $this->attach($incoming)->apply(new StoredTree([]));

        $carried = $result->roots[0]->property('text');
        static::assertNotNull($carried);
        static::assertObjectEquals(StoredValue::fromDecoded($translations), $carried);
    }

    #[TestDox('wires and attributes the type default binding on the root and nested elements of an unwired subtree')]
    public function testAttachAppliesTypeDefaultBindingToUnwiredSubtree(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $default = new BindingSpecification(
            'Sw:Card',
            'Sw:Card',
            'Card',
            ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])],
            [],
            'core',
        );
        $bindingRegistry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $bindingRegistry->method('all')->willReturn(['core:Sw:Card' => $default]);
        $serializers = static::createStub(DataLoaderConfigSerializerProvider::class);
        $serializers->method('decode')->willReturnCallback(static function (string $source, array $data) use ($config): AbstractContentDataLoaderConfig {
            static::assertSame('entity', $source);
            static::assertSame(['entity' => 'media', 'property' => 'mediaId'], $data);

            return $config;
        });
        $incoming = new StoredElement('incoming', 'Sw:Card', [], [], [
            'content' => [new StoredElement('incoming-child', 'Sw:Card')],
        ]);

        $attach = new AttachElement(
            $this->registry(),
            $incoming,
            $bindingRegistry,
            new BindingApplicator($serializers, static::createStub(AbstractContentSystemElementTypeRegistry::class)),
        );
        $result = $attach->apply(new StoredTree([]));

        $attached = $result->roots[0];
        $child = $attached->slots['content'][0];
        $wiring = ['media' => new DataRequirement('media', 'entity', $config)];
        static::assertEquals($wiring, $attached->dataRequirements);
        static::assertSame(['media' => 'core:Sw:Card'], $attached->attributedSpecifications);
        static::assertEquals($wiring, $child->dataRequirements);
        static::assertSame(['media' => 'core:Sw:Card'], $child->attributedSpecifications);
    }

    #[TestDox('names the element id the caller supplied when the type default binding rejects the root source')]
    public function testAttachRejectionNamesTheSuppliedElementId(): void
    {
        $default = new BindingSpecification(
            'Sw:Card',
            'Sw:Card',
            'Card',
            ['media' => new LoaderBinding('entity', ['entity' => [RootSourceConfigMap::MARKER => ['product' => 'media']]])],
            [],
            'core',
        );
        $bindingRegistry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $bindingRegistry->method('all')->willReturn(['core:Sw:Card' => $default]);

        $attach = new AttachElement(
            $this->registry(),
            new StoredElement('incoming', 'Sw:Card'),
            $bindingRegistry,
            new BindingApplicator(static::createStub(DataLoaderConfigSerializerProvider::class), static::createStub(AbstractContentSystemElementTypeRegistry::class)),
        );

        $this->expectExceptionObject(ContentSystemException::bindingRootSourceNotScoped('core:Sw:Card', 'media', 'incoming', 'category'));
        $attach->apply(new StoredTree([], 'category'));
    }

    #[TestDox('detaches nothing: orphaned and dropped wiring stay empty')]
    public function testAttachDetachesNothing(): void
    {
        $attach = $this->attach(new StoredElement('incoming', 'Sw:Card'));
        $attach->apply(new StoredTree([]));

        static::assertSame([], $attach->orphaned());
        static::assertSame([], $attach->droppedWiring());
    }

    #[TestDox('rejects an unregistered root component with a 400')]
    public function testAttachUnregisteredComponentRejected(): void
    {
        $attach = $this->attach(new StoredElement('incoming', 'Sw:Ghost'));

        $this->expectExceptionObject(ContentSystemException::mutationUnknownType('Sw:Ghost'));
        $attach->apply(new StoredTree([]));
    }

    #[TestDox('rejects attaching into a parent absent from the tree with a 400')]
    public function testAttachIntoMissingParentRejected(): void
    {
        $attach = $this->attach(new StoredElement('incoming', 'Sw:Card'), 'ghost', 'content');

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $attach->apply(new StoredTree([new StoredElement('other', 'Sw:Block')]));
    }

    #[TestDox('rejects attaching into a parent without naming a slot with a 400')]
    public function testAttachIntoParentWithoutSlotRejected(): void
    {
        $attach = $this->attach(new StoredElement('incoming', 'Sw:Card'), 'parent');

        $this->expectExceptionObject(ContentSystemException::mutationSlotRequired());
        $attach->apply(new StoredTree([new StoredElement('parent', 'Sw:Block')]));
    }

    private function attach(StoredElement $element, ?string $parentElementId = null, ?string $slot = null, ?int $index = null): AttachElement
    {
        return new AttachElement(
            $this->registry(),
            $element,
            static::createStub(AbstractContentSystemBindingSpecificationRegistry::class),
            new BindingApplicator(static::createStub(DataLoaderConfigSerializerProvider::class), $this->registry()),
            $parentElementId,
            $slot,
            $index,
        );
    }

    private function registry(): AbstractContentSystemElementTypeRegistry
    {
        $registered = ['Sw:Card', 'Sw:Block'];

        $registry = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $registry->method('has')->willReturnCallback(static fn (string $name): bool => \in_array($name, $registered, true));

        return $registry;
    }
}
