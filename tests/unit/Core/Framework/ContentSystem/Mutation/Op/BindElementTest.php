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
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Mutation\Op\BindElement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BindElement::class)]
class BindElementTest extends TestCase
{
    #[TestDox('inlines the resolves entry as a DataRequirement, seeds the input default, and attributes the resolves key to the specification id')]
    public function testBindWiresResolvesSeedsDefaultsAndAttributesSpecification(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $tree = new StoredTree([new StoredElement('el', 'Sw:Product')]);

        $result = (new BindElement($this->registry(), 'spec-1', 'el', $this->applicator($config)))->apply($tree);

        static::assertEquals(['product' => new DataRequirement('product', 'entity', $config)], $result->roots[0]->dataRequirements);
        static::assertSame('123', $result->roots[0]->property('mediaId')?->jsonSerialize());
        static::assertSame(['product' => 'spec-1'], $result->roots[0]->attributedSpecifications);
    }

    #[TestDox('does not seed a property when the input has no default and the element lacks the key')]
    public function testBindDoesNotSeedInputWithoutDefaultForAbsentKey(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $tree = new StoredTree([new StoredElement('el', 'Sw:Product')]);

        $result = (new BindElement($this->registryWithoutInputDefault(), 'spec-1', 'el', $this->applicator($config)))->apply($tree);

        static::assertNull($result->roots[0]->property('mediaId'));
    }

    #[TestDox('does not overwrite an authored non-null value on the input key with the default')]
    public function testBindKeepsAuthoredValueOverDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $old = StoredElementBuilder::create('Sw:Product', 'el')->withProperty('mediaId', 'authored')->build();

        $result = (new BindElement($this->registry(), 'spec-1', 'el', $this->applicator($config)))->apply(new StoredTree([$old]));

        static::assertSame('authored', $result->roots[0]->property('mediaId')?->jsonSerialize());
    }

    #[TestDox('replaces the wiring and attribution of a key already bound by a different specification')]
    public function testBindReplacesWiringAndAttributionOfAlreadyBoundKey(): void
    {
        $oldConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $newConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $old = StoredElementBuilder::create('Sw:Product', 'el')
            ->withDataRequirement('product', 'entity', $oldConfig)
            ->withAttributedSpecification('product', 'spec-old')
            ->withProperty('mediaId', 'user-filled')
            ->build();

        $result = (new BindElement($this->registry(), 'spec-1', 'el', $this->applicator($newConfig)))->apply(new StoredTree([$old]));

        static::assertEquals(['product' => new DataRequirement('product', 'entity', $newConfig)], $result->roots[0]->dataRequirements);
        static::assertSame(['product' => 'spec-1'], $result->roots[0]->attributedSpecifications);
        static::assertSame('user-filled', $result->roots[0]->property('mediaId')?->jsonSerialize());
    }

    #[TestDox('reports the bound element as affected')]
    public function testReportsAffectedElementAndNoDetachment(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $bind = new BindElement($this->registry(), 'spec-1', 'el', $this->applicator($config));

        $bind->apply(new StoredTree([new StoredElement('el', 'Sw:Product')]));

        // Bind never detaches anything: orphaned()/droppedWiring()/droppedProperties() are always empty for
        // this operation, so asserting them here would be trivially true regardless of the scenario above.
        static::assertSame(['el'], $bind->affected());
    }

    #[TestDox('does not overwrite an authored explicit null on the input key with the default')]
    public function testBindKeepsAuthoredExplicitNullOverDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $old = StoredElementBuilder::create('Sw:Product', 'el')->withProperty('mediaId', null)->build();

        $result = (new BindElement($this->registry(), 'spec-1', 'el', $this->applicator($config)))->apply(new StoredTree([$old]));

        static::assertTrue($result->roots[0]->property('mediaId')?->isNull());
    }

    #[TestDox('rejects a specification whose type does not match the target element component with a 400')]
    public function testBindTypeMismatchRejected(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $bind = new BindElement($this->registry(), 'spec-1', 'el', $this->applicator($config));

        $this->expectExceptionObject(ContentSystemException::bindingTypeMismatch('spec-1', 'Sw:Product', 'Sw:Other'));
        $bind->apply(new StoredTree([new StoredElement('el', 'Sw:Other')]));
    }

    #[TestDox('rejects an unknown binding specification id with a 400')]
    public function testBindUnknownSpecificationIdRejected(): void
    {
        $registry = static::createStub(AbstractContentSystemBindingSpecificationRegistry::class);
        $registry->method('all')->willReturn([]);
        $bind = new BindElement($registry, 'ghost', 'el', $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class)));

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationNotFound('ghost'));
        $bind->apply(new StoredTree([new StoredElement('el', 'Sw:Product')]));
    }

    #[TestDox('rejects binding an element absent from the tree with a 400')]
    public function testBindMissingElementRejected(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $bind = new BindElement($this->registry(), 'spec-1', 'ghost', $this->applicator($config));

        $this->expectExceptionObject(ContentSystemException::mutationTargetNotFound('ghost'));
        $bind->apply(new StoredTree([new StoredElement('el', 'Sw:Product')]));
    }

    private function registry(): AbstractContentSystemBindingSpecificationRegistry
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

    private function registryWithoutInputDefault(): AbstractContentSystemBindingSpecificationRegistry
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
