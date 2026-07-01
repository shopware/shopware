<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Specification\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;

/**
 * @internal
 */
#[CoversClass(BindingSpecificationDto::class)]
class BindingSpecificationDtoTest extends TestCase
{
    #[TestDox('maps type, label, source and the supplied id onto the specification')]
    public function testMapsFieldsOntoSpecification(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'From media library', [], []);

        $specification = $dto->toBindingSpecification('from-media-library', 'core');

        static::assertSame('from-media-library', $specification->id());
        static::assertSame('media-gallery', $specification->type());
        static::assertSame('From media library', $specification->label());
        static::assertSame('core', $specification->source());
    }

    #[TestDox('renames the wire key "loader" onto the LoaderBinding source property')]
    public function testResolvesEntryLoaderBecomesLoaderBindingSource(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [
            'image' => ['loader' => 'entity', 'config' => ['entity' => 'media']],
        ], []);

        $resolves = $dto->toBindingSpecification('id', 'core')->resolves();

        static::assertArrayHasKey('image', $resolves);
        static::assertSame('entity', $resolves['image']->source());
        static::assertSame(['entity' => 'media'], $resolves['image']->config());
    }

    #[TestDox('defaults a missing resolves config to an empty array')]
    public function testResolvesEntryWithoutConfigDefaultsToEmptyArray(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [
            'image' => ['loader' => 'entity'],
        ], []);

        $resolves = $dto->toBindingSpecification('id', 'core')->resolves();

        static::assertSame([], $resolves['image']->config());
    }

    #[TestDox('drops a resolves entry whose loader is not a string')]
    public function testDropsResolvesEntryWithNonStringLoader(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [
            'image' => ['loader' => 42, 'config' => []],
        ], []);

        static::assertSame([], $dto->toBindingSpecification('id', 'core')->resolves());
    }

    #[TestDox('drops a resolves entry that is not an array')]
    public function testDropsResolvesEntryThatIsNotAnArray(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [
            'image' => 'not-an-array',
        ], []);

        static::assertSame([], $dto->toBindingSpecification('id', 'core')->resolves());
    }

    #[TestDox('builds an empty resolves map when resolves is not an array')]
    public function testBuildsEmptyResolvesMapWhenResolvesIsNotAnArray(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', 'not-an-array', []);

        static::assertSame([], $dto->toBindingSpecification('id', 'core')->resolves());
    }

    #[TestDox('an inputs entry with a default has hasDefault true and carries the default value')]
    public function testInputsEntryWithDefaultHasDefaultTrue(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], [
            'alt' => ['default' => 'fallback alt'],
        ]);

        $inputs = $dto->toBindingSpecification('id', 'core')->inputs();

        static::assertTrue($inputs['alt']->hasDefault());
        static::assertSame('fallback alt', $inputs['alt']->default());
    }

    #[TestDox('an inputs entry without a default key has hasDefault false')]
    public function testInputsEntryWithoutDefaultKeyHasDefaultFalse(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], [
            'alt' => [],
        ]);

        static::assertFalse($dto->toBindingSpecification('id', 'core')->inputs()['alt']->hasDefault());
    }

    #[TestDox('an inputs entry with an explicit null default has hasDefault true and default null')]
    public function testInputsEntryWithExplicitNullDefaultHasDefaultTrue(): void
    {
        // Load-bearing: buildInputs() keys on array_key_exists('default', ...), so an explicit null default
        // is distinct from an absent one. A regression to a null-coalescing check would collapse the two.
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], [
            'alt' => ['default' => null],
        ]);

        $input = $dto->toBindingSpecification('id', 'core')->inputs()['alt'];

        static::assertTrue($input->hasDefault());
        static::assertNull($input->default());
    }

    #[TestDox('drops an inputs entry that is not an array')]
    public function testDropsInputsEntryThatIsNotAnArray(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], [
            'alt' => 'not-an-array',
        ]);

        static::assertSame([], $dto->toBindingSpecification('id', 'core')->inputs());
    }

    #[TestDox('builds an empty inputs map when inputs is not an array')]
    public function testBuildsEmptyInputsMapWhenInputsIsNotAnArray(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], 'not-an-array');

        static::assertSame([], $dto->toBindingSpecification('id', 'core')->inputs());
    }

    #[TestDox('narrows a non-string type to an empty string')]
    public function testNarrowsNonStringTypeToEmptyString(): void
    {
        $dto = new BindingSpecificationDto(42, 'label', [], []);

        static::assertSame('', $dto->toBindingSpecification('id', 'core')->type());
    }

    #[TestDox('narrows a non-string label to an empty string')]
    public function testNarrowsNonStringLabelToEmptyString(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', false, [], []);

        static::assertSame('', $dto->toBindingSpecification('id', 'core')->label());
    }
}
