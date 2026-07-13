<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mutation\Op;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Registry\AbstractContentSystemBindingSpecificationRegistry;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingInput;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\BindElement;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(BindElement::class)]
class BindElementTest extends TestCase
{
    use AssertsImmutableInput;

    #[TestDox('inlines the resolves entry as a DataRequirement, seeds the input default, and attributes the resolves key to the specification id')]
    public function testBindWiresResolvesSeedsDefaultsAndAttributesSpecification(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $tree = [new ContentElement('el', 'Sw:Product')];

        $result = (new BindElement($this->registry($config), 'spec-1', 'el', $this->applicator($config)))->apply($tree);

        static::assertEquals(['product' => new DataRequirement('product', 'entity', $config)], $result[0]->getDataRequirements());
        static::assertSame('123', $result[0]->getProperty('mediaId'));
        static::assertSame(['product' => 'spec-1'], $result[0]->getAttributedSpecifications());
    }

    #[TestDox('does not seed a property when the input has no default and the element lacks the key')]
    public function testBindDoesNotSeedInputWithoutDefaultForAbsentKey(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $tree = [new ContentElement('el', 'Sw:Product')];

        $result = (new BindElement($this->registryWithoutInputDefault($config), 'spec-1', 'el', $this->applicator($config)))->apply($tree);

        static::assertFalse($result[0]->hasProperty('mediaId'));
    }

    #[TestDox('does not overwrite an authored non-null value on the input key with the default')]
    public function testBindKeepsAuthoredValueOverDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $old = ContentElementBuilder::create('Sw:Product', 'el')->withProperty('mediaId', 'authored')->build();

        $result = (new BindElement($this->registry($config), 'spec-1', 'el', $this->applicator($config)))->apply([$old]);

        static::assertSame('authored', $result[0]->getProperty('mediaId'));
    }

    #[TestDox('replaces the wiring and attribution of a key already bound by a different specification')]
    public function testBindReplacesWiringAndAttributionOfAlreadyBoundKey(): void
    {
        $oldConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $newConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $old = ContentElementBuilder::create('Sw:Product', 'el')
            ->withDataRequirement('product', 'entity', $oldConfig)
            ->withAttributedSpecification('product', 'spec-old')
            ->withProperty('mediaId', 'user-filled')
            ->build();

        $result = (new BindElement($this->registry($newConfig), 'spec-1', 'el', $this->applicator($newConfig)))->apply([$old]);

        static::assertEquals(['product' => new DataRequirement('product', 'entity', $newConfig)], $result[0]->getDataRequirements());
        static::assertSame(['product' => 'spec-1'], $result[0]->getAttributedSpecifications());
        static::assertSame('user-filled', $result[0]->getProperty('mediaId'));
    }

    #[TestDox('does not mutate the input tree')]
    public function testBindDoesNotMutateInput(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $tree = [new ContentElement('el', 'Sw:Product')];
        $before = $this->snapshotTree($tree);

        (new BindElement($this->registry($config), 'spec-1', 'el', $this->applicator($config)))->apply($tree);

        $this->assertInputTreeUnmutated($before, $tree);
    }

    #[TestDox('reports the bound element as affected and detaches nothing')]
    public function testReportsAffectedElementAndNoDetachment(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $bind = new BindElement($this->registry($config), 'spec-1', 'el', $this->applicator($config));

        $bind->apply([new ContentElement('el', 'Sw:Product')]);

        static::assertSame(['el'], $bind->affected());
        static::assertSame([], $bind->orphaned());
        static::assertSame([], $bind->droppedWiring());
        static::assertSame([], $bind->droppedProperties());
    }

    #[TestDox('does not overwrite an authored explicit null on the input key with the default')]
    public function testBindKeepsAuthoredExplicitNullOverDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $old = ContentElementBuilder::create('Sw:Product', 'el')->withProperty('mediaId', null)->build();

        $result = (new BindElement($this->registry($config), 'spec-1', 'el', $this->applicator($config)))->apply([$old]);

        static::assertTrue($result[0]->hasProperty('mediaId'));
        static::assertNull($result[0]->getProperty('mediaId'));
    }

    #[TestDox('preserves a pre-existing numeric-string-keyed data requirement and attribution instead of renumbering it')]
    public function testBindPreservesNumericStringKeyedWiringWithoutRenumbering(): void
    {
        $oldConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $newConfig = static::createStub(AbstractContentDataLoaderConfig::class);

        // '5' is a numeric-string property key: PHP casts it to the integer array key 5 on both
        // dataRequirements and attributedSpecifications. A `[...$a, ...$b]` spread merge renumbers integer
        // keys positionally instead of preserving them, which would silently drop key 5 from one or both
        // maps and desync them from `properties`/`acceptsContext` (still keyed '5'/5). array_replace()
        // preserves the key exactly, so this pre-existing pair must survive the bind untouched.
        $old = ContentElementBuilder::create('Sw:Product', 'el')
            ->withDataRequirement('5', 'entity', $oldConfig)
            ->withAttributedSpecification('5', 'spec-old')
            ->build();

        $result = (new BindElement($this->registry($newConfig), 'spec-1', 'el', $this->applicator($newConfig)))->apply([$old]);

        static::assertEquals(
            ['5' => new DataRequirement('5', 'entity', $oldConfig), 'product' => new DataRequirement('product', 'entity', $newConfig)],
            $result[0]->getDataRequirements()
        );
        static::assertSame(
            ['5' => 'spec-old', 'product' => 'spec-1'],
            $result[0]->getAttributedSpecifications()
        );
    }

    #[TestDox('rejects a specification whose type does not match the target element component with a 400')]
    public function testBindTypeMismatchRejected(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $bind = new BindElement($this->registry($config), 'spec-1', 'el', $this->applicator($config));

        try {
            $bind->apply([new ContentElement('el', 'Sw:Other')]);
            static::fail('Expected ContentSystemException was not thrown.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::BINDING_TYPE_MISMATCH, $e->getErrorCode());
        }
    }

    #[TestDox('rejects an unknown binding specification id with a 400')]
    public function testBindUnknownSpecificationIdRejected(): void
    {
        $registry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->method('all')->willReturn([]);
        $bind = new BindElement($registry, 'ghost', 'el', $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)));

        try {
            $bind->apply([new ContentElement('el', 'Sw:Product')]);
            static::fail('Expected ContentSystemException was not thrown.');
        } catch (ContentSystemException $e) {
            static::assertSame(ContentSystemException::BINDING_SPECIFICATION_NOT_FOUND, $e->getErrorCode());
        }
    }

    #[TestDox('rejects binding an element absent from the tree with a 400')]
    public function testBindMissingElementRejected(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $bind = new BindElement($this->registry($config), 'spec-1', 'ghost', $this->applicator($config));

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $bind->apply([new ContentElement('el', 'Sw:Product')]);
    }

    private function registry(AbstractContentDataLoaderConfig $config): AbstractContentSystemBindingSpecificationRegistry
    {
        $specification = new BindingSpecification(
            'spec-1',
            'Sw:Product',
            'Product binding',
            ['product' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])],
            ['mediaId' => new BindingInput(true, '123', false)],
            'core',
        );

        $registry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->method('all')->willReturn(['spec-1' => $specification]);

        return $registry;
    }

    private function registryWithoutInputDefault(AbstractContentDataLoaderConfig $config): AbstractContentSystemBindingSpecificationRegistry
    {
        $specification = new BindingSpecification(
            'spec-1',
            'Sw:Product',
            'Product binding',
            ['product' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])],
            ['mediaId' => new BindingInput(false, null, false)],
            'core',
        );

        $registry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->method('all')->willReturn(['spec-1' => $specification]);

        return $registry;
    }

    private function applicator(AbstractContentDataLoaderConfig $config): BindingApplicator
    {
        $serializers = static::createStub(DataLoaderConfigSerializerProvider::class);
        $serializers->method('decode')->willReturn($config);

        return new BindingApplicator($serializers);
    }
}
