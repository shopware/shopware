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
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\Style\ElementStyle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\ContentSystem\StoredElementBuilder;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BindingApplicator::class)]
class BindingApplicatorTest extends TestCase
{
    #[TestDox('decodes the resolves entry into a DataRequirement and attributes the resolves key to the given id')]
    public function testAppliesResolvesEntryAsDataRequirementAndAttributesIt(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = new StoredElement('img-1', 'Sw:Media:Image');

        $result = $this->applicator($config)->apply($element, $this->specification(new BindingInput(false, null, false)), 'core:media-picker');

        static::assertEquals(['media' => new DataRequirement('media', 'entity', $config)], $result->dataRequirements);
        static::assertSame(['media' => 'core:media-picker'], $result->attributedSpecifications);
    }

    #[TestDox('seeds the input default onto an input key the element does not yet carry')]
    public function testSeedsInputDefaultOntoAbsentKey(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = new StoredElement('img-1', 'Sw:Media:Image');

        $result = $this->applicator($config)->apply($element, $this->specification(new BindingInput(true, 'seeded', false)), 'core:media-picker');

        static::assertSame('seeded', $result->property('mediaId')?->jsonSerialize());
    }

    #[TestDox('overwrites the wiring and attribution of a key already bound by a different specification')]
    public function testOverwritesWiringAndAttributionForAlreadyBoundKey(): void
    {
        $oldConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $newConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = $this->boundImageElement($oldConfig);

        $result = $this->applicator($newConfig)->apply($element, $this->specification(new BindingInput(false, null, false)), 'core:media-picker');

        static::assertSame(['media'], array_keys($result->dataRequirements));
        static::assertSame('media', $result->dataRequirements['media']->key);
        static::assertSame('entity', $result->dataRequirements['media']->source);
        static::assertSame($newConfig, $result->dataRequirements['media']->config);
        static::assertSame(['media' => 'core:media-picker'], $result->attributedSpecifications);
    }

    #[TestDox('keeps the wiring of keys the specification does not declare')]
    public function testKeepsWiringOfKeysOutsideTheSpecification(): void
    {
        $galleryConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $mediaConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = StoredElementBuilder::create('Sw:Media:Image', 'img-1')
            ->withDataRequirement('gallery', 'entity_collection', $galleryConfig)
            ->withAttributedSpecification('gallery', 'core:gallery-spec')
            ->build();

        $result = $this->applicator($mediaConfig)->apply($element, $this->specification(new BindingInput(false, null, false)), 'core:media-picker');

        static::assertSame(['gallery', 'media'], array_keys($result->dataRequirements));
        static::assertSame($galleryConfig, $result->dataRequirements['gallery']->config);
        static::assertSame($mediaConfig, $result->dataRequirements['media']->config);
        static::assertSame(['gallery' => 'core:gallery-spec', 'media' => 'core:media-picker'], $result->attributedSpecifications);
    }

    #[TestDox('decodes each resolves entry with its own loader and config')]
    public function testDecodesEachResolvesEntryWithItsOwnBinding(): void
    {
        $mediaConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $galleryConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $serializers = static::createStub(DataLoaderConfigSerializerProvider::class);
        $serializers->method('decode')->willReturnMap([
            ['entity', ['entity' => 'media', 'property' => 'mediaId'], $mediaConfig],
            ['entity_collection', ['entity' => 'media', 'property' => 'galleryIds'], $galleryConfig],
        ]);
        $element = new StoredElement('img-1', 'Sw:Media:Image');

        $result = (new BindingApplicator($serializers))->apply($element, $this->twoKeySpecification(), 'core:media-picker');

        static::assertSame($mediaConfig, $result->dataRequirements['media']->config);
        static::assertSame($galleryConfig, $result->dataRequirements['gallery']->config);
    }

    #[TestDox('fill-only: wires a resolves entry into a key the element has no data requirement for, and attributes it')]
    public function testFillOnlyWiresAbsentKeyAndAttributes(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = new StoredElement('img-1', 'Sw:Media:Image');

        $result = $this->applicator($config)->applyFillOnly($element, $this->specification(new BindingInput(false, null, false)), 'core:Sw:Media:Image');

        static::assertEquals(['media' => new DataRequirement('media', 'entity', $config)], $result->dataRequirements);
        static::assertSame(['media' => 'core:Sw:Media:Image'], $result->attributedSpecifications);
    }

    #[TestDox('fill-only: does not overwrite the wiring or attribution of a key already bound by a different specification')]
    public function testFillOnlyDoesNotOverwriteAlreadyBoundKey(): void
    {
        $oldConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $newConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = $this->boundImageElement($oldConfig);

        $result = $this->applicator($newConfig)->applyFillOnly($element, $this->specification(new BindingInput(false, null, false)), 'core:Sw:Media:Image');

        static::assertSame(['media'], array_keys($result->dataRequirements));
        static::assertSame('media', $result->dataRequirements['media']->key);
        static::assertSame('entity', $result->dataRequirements['media']->source);
        static::assertSame($oldConfig, $result->dataRequirements['media']->config);
        static::assertSame(['media' => 'core:old-spec'], $result->attributedSpecifications);
    }

    #[TestDox('fill-only: records attribution only for the keys it actually wired, not an already-bound key the specification also declares')]
    public function testFillOnlyAttributesOnlyTheKeysItWired(): void
    {
        $oldConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $newConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = $this->boundImageElement($oldConfig);

        $result = $this->applicator($newConfig)->applyFillOnly($element, $this->twoKeySpecification(), 'core:Sw:Media:Image');

        static::assertSame(['media' => 'core:old-spec', 'gallery' => 'core:Sw:Media:Image'], $result->attributedSpecifications);
        static::assertSame('media', $result->dataRequirements['media']->key);
        static::assertSame('entity', $result->dataRequirements['media']->source);
        static::assertSame($oldConfig, $result->dataRequirements['media']->config);
        static::assertSame('gallery', $result->dataRequirements['gallery']->key);
        static::assertSame('entity_collection', $result->dataRequirements['gallery']->source);
        static::assertSame($newConfig, $result->dataRequirements['gallery']->config);
    }

    #[TestDox('fill-only: leaves an already wired key unattributed when it carries no prior attribution entry')]
    public function testFillOnlyLeavesAnAlreadyWiredButUnattributedKeyUnattributed(): void
    {
        // 'media' already has a data requirement but no attribution entry. wiredKeys at BindingApplicator.php:48
        // is array_diff'd against the existing data requirement keys, so it excludes 'media' and attributionFor()
        // mints no entry for it. Every fill-only test that exercises an already-bound key builds it via
        // boundImageElement(), which sets an attribution alongside the data requirement, so the `+` union at :52
        // masks whether wiredKeys correctly excluded the key: replacing it with array_keys($specification->resolves())
        // would mint an attribution entry here that the key never had, and the old entry's absence would then show.
        $oldConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $newConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = StoredElementBuilder::create('Sw:Media:Image', 'img-1')
            ->withDataRequirement('media', 'entity', $oldConfig)
            ->build();

        $result = $this->applicator($newConfig)->applyFillOnly($element, $this->specification(new BindingInput(false, null, false)), 'core:media-picker');

        static::assertSame([], $result->attributedSpecifications);
    }

    #[TestDox('does not seed an input whose specification declares no default')]
    public function testDoesNotSeedInputWithoutDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = new StoredElement('img-1', 'Sw:Media:Image');

        $result = $this->applicator($config)->apply($element, $this->specification(new BindingInput(false, null, false)), 'core:media-picker');

        static::assertNull($result->property('mediaId'));
    }

    #[TestDox('keeps an authored value on the input key instead of overwriting it with the default')]
    public function testKeepsAuthoredValueOverDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = StoredElementBuilder::create('Sw:Media:Image', 'img-1')->withProperty('mediaId', 'authored')->build();

        $result = $this->applicator($config)->apply($element, $this->specification(new BindingInput(true, 'seeded', false)), 'core:media-picker');

        static::assertSame('authored', $result->property('mediaId')?->jsonSerialize());
    }

    #[TestDox('keeps an authored explicit null on the input key instead of overwriting it with the default')]
    public function testKeepsAuthoredExplicitNullOverDefault(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $element = StoredElementBuilder::create('Sw:Media:Image', 'img-1')->withProperty('mediaId', null)->build();

        $result = $this->applicator($config)->apply($element, $this->specification(new BindingInput(true, 'seeded', false)), 'core:media-picker');

        static::assertTrue($result->property('mediaId')?->isNull());
    }

    #[TestDox('seeds a later input default after an input that declares no default')]
    public function testSeedsLaterInputDefaultAfterInputWithoutDefault(): void
    {
        $element = new StoredElement('img-1', 'Sw:Media:Image');
        $specification = new BindingSpecification(
            'media-picker',
            'Sw:Media:Image',
            'Media picker',
            [],
            ['first' => new BindingInput(false, null, false), 'mediaId' => new BindingInput(true, 'seeded', false)],
            'core',
        );

        $result = $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class))->apply($element, $specification, 'core:media-picker');

        static::assertSame('seeded', $result->property('mediaId')?->jsonSerialize());
    }

    #[TestDox('seeds a later input default after an input the element already carries')]
    public function testSeedsLaterInputDefaultAfterAlreadyAuthoredInput(): void
    {
        $element = StoredElementBuilder::create('Sw:Media:Image', 'img-1')->withProperty('first', 'authored')->build();
        $specification = new BindingSpecification(
            'media-picker',
            'Sw:Media:Image',
            'Media picker',
            [],
            ['first' => new BindingInput(true, 'seeded', false), 'mediaId' => new BindingInput(true, 'seeded', false)],
            'core',
        );

        $result = $this->applicator(static::createStub(AbstractContentDataLoaderConfig::class))->apply($element, $specification, 'core:media-picker');

        static::assertSame('seeded', $result->property('mediaId')?->jsonSerialize());
    }

    #[TestDox('rebuilds the element preserving its id, component, slots, style, and context definitions')]
    public function testPreservesElementIdentitySlotsStyleAndContext(): void
    {
        $config = static::createStub(AbstractContentDataLoaderConfig::class);
        $style = new ElementStyle(['padding' => ['md' => '1rem']]);
        $element = StoredElementBuilder::create('Sw:Media:Image', 'img-1')
            ->withSlot('content', [new StoredElement('child', 'Sw:Content:Text')])
            ->withStyle($style)
            ->build();

        $result = $this->applicator($config)->apply($element, $this->specification(new BindingInput(false, null, false)), 'core:media-picker');

        static::assertSame('img-1', $result->id);
        static::assertSame('Sw:Media:Image', $result->component);
        static::assertSame($style, $result->style);
        static::assertSame($element->slots, $result->slots);
        static::assertSame($element->contextDefinitions, $result->contextDefinitions);
    }

    private function boundImageElement(AbstractContentDataLoaderConfig $oldConfig): StoredElement
    {
        return StoredElementBuilder::create('Sw:Media:Image', 'img-1')
            ->withDataRequirement('media', 'entity', $oldConfig)
            ->withAttributedSpecification('media', 'core:old-spec')
            ->build();
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
