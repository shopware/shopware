<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Specification\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[TestDox('renames the wire key "loader" onto the LoaderBinding loader property')]
    public function testResolvesEntryLoaderBecomesLoaderBindingLoader(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [
            'image' => ['loader' => 'entity', 'config' => ['entity' => 'media']],
        ], []);

        $resolves = $dto->toBindingSpecification('id', 'core')->resolves();

        static::assertArrayHasKey('image', $resolves);
        static::assertSame('entity', $resolves['image']->loader);
        static::assertSame(['entity' => 'media'], $resolves['image']->config);
    }

    #[TestDox('defaults a missing resolves config to an empty array')]
    public function testResolvesEntryWithoutConfigDefaultsToEmptyArray(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [
            'image' => ['loader' => 'entity'],
        ], []);

        $resolves = $dto->toBindingSpecification('id', 'core')->resolves();

        static::assertSame([], $resolves['image']->config);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function buildsEmptyResolvesMapProvider(): iterable
    {
        yield 'top-level resolves is not an array' => ['not-an-array'];
        yield 'entry is not an array' => [['image' => 'not-an-array']];
        yield 'entry loader is not a string' => [['image' => ['loader' => 42, 'config' => []]]];
    }

    #[DataProvider('buildsEmptyResolvesMapProvider')]
    #[TestDox('builds an empty resolves map for $_dataName')]
    public function testBuildsEmptyResolvesMap(mixed $resolves): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', $resolves, []);

        static::assertSame([], $dto->toBindingSpecification('id', 'core')->resolves());
    }

    #[TestDox('sets hasDefault true and carries the default value for an inputs entry with a default')]
    public function testInputsEntryWithDefaultHasDefaultTrue(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], [
            'alt' => ['default' => 'fallback alt'],
        ]);

        $inputs = $dto->toBindingSpecification('id', 'core')->inputs();

        static::assertTrue($inputs['alt']->hasDefault);
        static::assertSame('fallback alt', $inputs['alt']->default);
    }

    #[TestDox('sets hasDefault false when an inputs entry has no default key')]
    public function testInputsEntryWithoutDefaultKeyHasDefaultFalse(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], [
            'alt' => [],
        ]);

        static::assertFalse($dto->toBindingSpecification('id', 'core')->inputs()['alt']->hasDefault);
    }

    #[TestDox('sets hasDefault true and default null for an entry with an explicit null default')]
    public function testInputsEntryWithExplicitNullDefaultHasDefaultTrue(): void
    {
        // Load-bearing: buildInputs() keys on array_key_exists('default', ...), so an explicit null default
        // is distinct from an absent one. A regression to a null-coalescing check would collapse the two.
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], [
            'alt' => ['default' => null],
        ]);

        $input = $dto->toBindingSpecification('id', 'core')->inputs()['alt'];

        static::assertTrue($input->hasDefault);
        static::assertNull($input->default);
    }

    /**
     * @param array<string, mixed> $entry
     */
    #[DataProvider('inputRequiredProvider')]
    #[TestDox('sets BindingInput::required to $expected when the inputs entry $_dataName')]
    public function testInputsEntryRequiredFlag(array $entry, bool $expected): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], ['alt' => $entry]);

        static::assertSame($expected, $dto->toBindingSpecification('id', 'core')->inputs()['alt']->required);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function inputRequiredProvider(): iterable
    {
        yield 'carries required:true' => [['required' => true], true];
        yield 'carries required:false' => [['required' => false], false];
        yield 'has no required key' => [[], false];
        // Load-bearing: buildInputs() passes required only on a strict === true, never coercing a truthy value.
        yield 'carries a non-boolean truthy required' => [['required' => 1], false];
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

    /**
     * @param mixed $promoted the raw promoted facet as carried from the declaration
     */
    #[DataProvider('promotedProvider')]
    #[TestDox('maps isPromoted() to $expected when the raw promoted facet $_dataName')]
    public function testMapsPromotedFacet(mixed $promoted, bool $expected): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], [], $promoted);

        static::assertSame($expected, $dto->toBindingSpecification('id', 'core')->isPromoted());
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function promotedProvider(): iterable
    {
        yield 'is true' => [true, true];
        yield 'is false' => [false, false];
        yield 'is absent (defaulted null)' => [null, false];
        // Load-bearing: toBindingSpecification() maps on a strict === true, never coercing a truthy value.
        yield 'is a truthy non-boolean' => [1, false];
    }

    #[TestDox('defaults isPromoted() to false when the promoted facet is omitted from the constructor')]
    public function testDefaultsPromotedToFalseWhenOmitted(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], []);

        static::assertFalse($dto->toBindingSpecification('id', 'core')->isPromoted());
    }
}
