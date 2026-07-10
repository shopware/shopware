<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\BindingApplicator;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingInput;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\LoaderBinding;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;

/**
 * @internal
 */
#[CoversClass(BindingApplicator::class)]
class BindingApplicatorTest extends TestCase
{
    #[TestDox('decodes the resolves entry into a DataRequirement, seeds the input default, and attributes the resolves key to the given id')]
    public function testAppliesResolvesSeedsDefaultAndAttributes(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = new ContentElement('img-1', 'Sw:Media:Image');

        $result = $this->applicator($config)->apply($element, $this->specification(new BindingInput(true, 'seeded', false)), 'core:media-picker');

        static::assertEquals(['media' => new DataRequirement('media', 'entity', $config)], $result->getDataRequirements());
        static::assertSame('seeded', $result->getProperty('mediaId'));
        static::assertSame(['media' => 'core:media-picker'], $result->getAttributedSpecifications());
    }

    #[TestDox('overwrites the wiring and attribution of a key already bound by a different specification')]
    public function testOverwritesWiringAndAttributionForAlreadyBoundKey(): void
    {
        $oldConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $newConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = ContentElementBuilder::create('Sw:Media:Image', 'img-1')
            ->withDataRequirement('media', 'entity', $oldConfig)
            ->withAttributedSpecification('media', 'core:old-spec')
            ->build();

        $result = $this->applicator($newConfig)->apply($element, $this->specification(new BindingInput(false, null, false)), 'core:media-picker');

        static::assertEquals(['media' => new DataRequirement('media', 'entity', $newConfig)], $result->getDataRequirements());
        static::assertSame(['media' => 'core:media-picker'], $result->getAttributedSpecifications());
    }

    #[TestDox('fill-only: wires a resolves entry into a key the element has no data requirement for, and attributes it')]
    public function testFillOnlyWiresAbsentKeyAndAttributes(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = new ContentElement('img-1', 'Sw:Media:Image');

        $result = $this->applicator($config)->applyFillOnly($element, $this->specification(new BindingInput(false, null, false)), 'core:Sw:Media:Image');

        static::assertEquals(['media' => new DataRequirement('media', 'entity', $config)], $result->getDataRequirements());
        static::assertSame(['media' => 'core:Sw:Media:Image'], $result->getAttributedSpecifications());
    }

    #[TestDox('fill-only: does not overwrite the wiring or attribution of a key already bound by a different specification')]
    public function testFillOnlyDoesNotOverwriteAlreadyBoundKey(): void
    {
        $oldConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $newConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = ContentElementBuilder::create('Sw:Media:Image', 'img-1')
            ->withDataRequirement('media', 'entity', $oldConfig)
            ->withAttributedSpecification('media', 'core:old-spec')
            ->build();

        $result = $this->applicator($newConfig)->applyFillOnly($element, $this->specification(new BindingInput(false, null, false)), 'core:Sw:Media:Image');

        static::assertEquals(['media' => new DataRequirement('media', 'entity', $oldConfig)], $result->getDataRequirements());
        static::assertSame(['media' => 'core:old-spec'], $result->getAttributedSpecifications());
    }

    #[TestDox('fill-only: records attribution only for the keys it actually wired, not an already-bound key the specification also declares')]
    public function testFillOnlyAttributesOnlyTheKeysItWired(): void
    {
        $oldConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $newConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = ContentElementBuilder::create('Sw:Media:Image', 'img-1')
            ->withDataRequirement('media', 'entity', $oldConfig)
            ->withAttributedSpecification('media', 'core:old-spec')
            ->build();

        $result = $this->applicator($newConfig)->applyFillOnly($element, $this->twoKeySpecification(), 'core:Sw:Media:Image');

        static::assertSame(['media' => 'core:old-spec', 'gallery' => 'core:Sw:Media:Image'], $result->getAttributedSpecifications());
        static::assertEquals(new DataRequirement('media', 'entity', $oldConfig), $result->getDataRequirements()['media']);
        static::assertEquals(new DataRequirement('gallery', 'entity_collection', $newConfig), $result->getDataRequirements()['gallery']);
    }

    #[TestDox('does not seed an input whose specification declares no default')]
    public function testDoesNotSeedInputWithoutDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = new ContentElement('img-1', 'Sw:Media:Image');

        $result = $this->applicator($config)->apply($element, $this->specification(new BindingInput(false, null, false)), 'core:media-picker');

        static::assertFalse($result->hasProperty('mediaId'));
    }

    #[TestDox('keeps an authored value on the input key instead of overwriting it with the default')]
    public function testKeepsAuthoredValueOverDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = ContentElementBuilder::create('Sw:Media:Image', 'img-1')->withProperty('mediaId', 'authored')->build();

        $result = $this->applicator($config)->apply($element, $this->specification(new BindingInput(true, 'seeded', false)), 'core:media-picker');

        static::assertSame('authored', $result->getProperty('mediaId'));
    }

    #[TestDox('keeps an authored explicit null on the input key instead of overwriting it with the default')]
    public function testKeepsAuthoredExplicitNullOverDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = ContentElementBuilder::create('Sw:Media:Image', 'img-1')->withProperty('mediaId', null)->build();

        $result = $this->applicator($config)->apply($element, $this->specification(new BindingInput(true, 'seeded', false)), 'core:media-picker');

        static::assertTrue($result->hasProperty('mediaId'));
        static::assertNull($result->getProperty('mediaId'));
    }

    #[TestDox('rebuilds the element preserving its id, component, slots, style, and context definitions')]
    public function testPreservesElementIdentitySlotsStyleAndContext(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $style = new ElementStyle(['padding' => ['md' => '1rem']]);
        $element = ContentElementBuilder::create('Sw:Media:Image', 'img-1')
            ->withSlot('content', [new ContentElement('child', 'Sw:Content:Text')])
            ->withStyle($style)
            ->build();

        $result = $this->applicator($config)->apply($element, $this->specification(new BindingInput(false, null, false)), 'core:media-picker');

        static::assertSame('img-1', $result->getId());
        static::assertSame('Sw:Media:Image', $result->getComponent());
        static::assertSame($style, $result->getStyle());
        static::assertSame($element->getSlots(), $result->getSlots());
        static::assertSame($element->getContextDefinitions(), $result->getContextDefinitions());
    }

    #[TestDox('rebuilds a new element and leaves the input element untouched')]
    public function testDoesNotMutateTheInputElement(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = new ContentElement('img-1', 'Sw:Media:Image');

        $this->applicator($config)->apply($element, $this->specification(new BindingInput(true, 'seeded', false)), 'core:media-picker');

        static::assertSame([], $element->getDataRequirements());
        static::assertSame([], $element->getProperties());
        static::assertSame([], $element->getAttributedSpecifications());
    }

    private function applicator(AbstractContentDataLoaderConfig $config): BindingApplicator
    {
        $serializers = static::createStub(DataLoaderConfigSerializerProvider::class);
        $serializers->method('decode')->willReturn($config);

        return new BindingApplicator($serializers);
    }

    private function specification(BindingInput $mediaIdInput): BindingSpecification
    {
        return new BindingSpecification(
            'media-picker',
            'Sw:Media:Image',
            'Media picker',
            ['media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId'])],
            ['mediaId' => $mediaIdInput],
            'core',
        );
    }

    private function twoKeySpecification(): BindingSpecification
    {
        return new BindingSpecification(
            'Sw:Media:Image',
            'Sw:Media:Image',
            'Image',
            [
                'media' => new LoaderBinding('entity', ['entity' => 'media', 'property' => 'mediaId']),
                'gallery' => new LoaderBinding('entity_collection', ['entity' => 'media', 'property' => 'galleryIds']),
            ],
            [],
            'core',
        );
    }
}
