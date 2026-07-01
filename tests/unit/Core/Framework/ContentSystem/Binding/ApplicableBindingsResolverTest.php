<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\ApplicableBindingsResolver;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(ApplicableBindingsResolver::class)]
class ApplicableBindingsResolverTest extends TestCase
{
    #[TestDox('maps each root element to only its own type\'s specifications, as source-qualified ids')]
    public function testResolveMapsEachElementToItsTypeSpecificationsOnly(): void
    {
        $mediaSpec = new BindingSpecification('from-media-library', 'Sw:Media', 'From Media Library', [], [], 'core');
        $productSpec = new BindingSpecification('from-product-list', 'Sw:Product', 'From Product List', [], [], 'core');

        $resolver = new ApplicableBindingsResolver($this->registry($mediaSpec, $productSpec));

        $tree = [
            new ContentElement('media-el', 'Sw:Media'),
            new ContentElement('product-el', 'Sw:Product'),
        ];

        static::assertSame(
            [
                'media-el' => ['core:from-media-library'],
                'product-el' => ['core:from-product-list'],
            ],
            $resolver->resolve($tree),
        );
    }

    #[TestDox('maps an element of a type with no registered specifications to an empty list')]
    public function testResolveMapsUnmatchedTypeToEmptyList(): void
    {
        $spec = new BindingSpecification('from-media-library', 'Sw:Media', 'From Media Library', [], [], 'core');

        $resolver = new ApplicableBindingsResolver($this->registry($spec));

        static::assertSame(
            ['no-spec-el' => []],
            $resolver->resolve([new ContentElement('no-spec-el', 'Sw:Other')]),
        );
    }

    #[TestDox('recurses into a slot child, mapping both the parent and the descendant by their own type')]
    public function testResolveRecursesIntoSlotChildren(): void
    {
        $mediaSpec = new BindingSpecification('from-media-library', 'Sw:Media', 'From Media Library', [], [], 'core');
        $productSpec = new BindingSpecification('from-product-list', 'Sw:Product', 'From Product List', [], [], 'core');

        $resolver = new ApplicableBindingsResolver($this->registry($mediaSpec, $productSpec));

        $child = ContentElementBuilder::create('Sw:Product', 'child-el')->build();
        $parent = ContentElementBuilder::create('Sw:Media', 'parent-el')->withSlot('content', [$child])->build();

        static::assertSame(
            [
                'parent-el' => ['core:from-media-library'],
                'child-el' => ['core:from-product-list'],
            ],
            $resolver->resolve([$parent]),
        );
    }

    #[TestDox('formats the qualified id as exactly "source:id"')]
    public function testResolveFormatsQualifiedIdAsSourceColonId(): void
    {
        $spec = new BindingSpecification('from-media-library', 'Sw:Media', 'From Media Library', [], [], 'plugin:Acme');

        $resolver = new ApplicableBindingsResolver($this->registry($spec));

        static::assertSame(
            ['media-el' => ['plugin:Acme:from-media-library']],
            $resolver->resolve([new ContentElement('media-el', 'Sw:Media')]),
        );
    }

    #[TestDox('looks up a repeated component\'s specifications once per resolve() call instead of once per element')]
    public function testResolveMemoizesRepeatedComponentWithinOneCall(): void
    {
        $spec = new BindingSpecification('from-media-library', 'Sw:Media', 'From Media Library', [], [], 'core');

        $registry = static::createMock(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->expects($this->once())->method('all')->willReturn(['core:from-media-library' => $spec]);

        $resolver = new ApplicableBindingsResolver($registry);

        $child = ContentElementBuilder::create('Sw:Media', 'child-el')->build();
        $parent = ContentElementBuilder::create('Sw:Media', 'parent-el')->withSlot('content', [$child])->build();
        $sibling = ContentElementBuilder::create('Sw:Media', 'sibling-el')->build();

        static::assertSame(
            [
                'parent-el' => ['core:from-media-library'],
                'child-el' => ['core:from-media-library'],
                'sibling-el' => ['core:from-media-library'],
            ],
            $resolver->resolve([$parent, $sibling]),
        );
    }

    private function registry(BindingSpecification ...$specifications): AbstractContentSystemBindingSpecificationRegistry
    {
        $all = [];
        foreach ($specifications as $specification) {
            $all[$specification->source() . ':' . $specification->id()] = $specification;
        }

        $registry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->method('all')->willReturn($all);

        return $registry;
    }
}
