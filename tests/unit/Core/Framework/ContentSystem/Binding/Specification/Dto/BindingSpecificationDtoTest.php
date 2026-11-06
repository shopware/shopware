<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding\Specification\Dto;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\BindingSpecification;
use Shopware\Core\Framework\ContentSystem\Binding\Specification\Dto\BindingSpecificationDto;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(BindingSpecificationDto::class)]
class BindingSpecificationDtoTest extends TestCase
{
    #[TestDox('maps type, label, source and the supplied id onto the specification')]
    public function testMapsFieldsOntoSpecification(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'From media library', [], []);

        $specification = $dto->toBindingSpecification('media-picker', 'core');

        static::assertSame('media-picker', $specification->id());
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

    /**
     * @param array<string, mixed> $entry
     */
    #[DataProvider('definesDefaultPresenceProvider')]
    #[TestDox('sets hasDefault and the default value for an inputs entry with $_dataName')]
    public function testDefinesDefaultPresence(array $entry, bool $expectedHasDefault, mixed $expectedDefault): void
    {
        $input = (new BindingSpecificationDto('media-gallery', 'label', [], ['alt' => $entry]))
            ->toBindingSpecification('id', 'core')
            ->inputs()['alt'];

        static::assertSame($expectedHasDefault, $input->hasDefault);
        static::assertSame($expectedDefault, $input->default);
    }

    /**
     * @param array<string, mixed> $entry
     */
    #[DataProvider('definesRequiredFlagProvider')]
    #[TestDox('sets BindingInput::required to $expected when the inputs entry $_dataName')]
    public function testSetsRequiredFlagFromInputsEntry(array $entry, bool $expected): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], ['alt' => $entry]);

        static::assertSame($expected, $dto->toBindingSpecification('id', 'core')->inputs()['alt']->required);
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

    #[DataProvider('buildsEmptyResolvesMapProvider')]
    #[TestDox('builds an empty resolves map for $_dataName')]
    public function testBuildsEmptyResolvesMap(mixed $resolves): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', $resolves, []);

        static::assertSame([], $dto->toBindingSpecification('id', 'core')->resolves());
    }

    #[TestDox('builds an empty inputs map when inputs is not an array')]
    public function testBuildsEmptyInputsMapWhenInputsIsNotAnArray(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], 'not-an-array');

        static::assertSame([], $dto->toBindingSpecification('id', 'core')->inputs());
    }

    #[TestDox('drops an inputs entry that is not an array')]
    public function testDropsInputsEntryThatIsNotAnArray(): void
    {
        $dto = new BindingSpecificationDto('media-gallery', 'label', [], [
            'alt' => 'not-an-array',
        ]);

        static::assertSame([], $dto->toBindingSpecification('id', 'core')->inputs());
    }

    /**
     * @param callable(BindingSpecification): string $accessor
     */
    #[DataProvider('narrowsNonStringToEmptyStringProvider')]
    #[TestDox('narrows a non-string value to an empty string ($_dataName)')]
    public function testNarrowsNonStringToEmptyString(mixed $type, mixed $label, callable $accessor): void
    {
        $specification = (new BindingSpecificationDto($type, $label, [], []))->toBindingSpecification('id', 'core');

        static::assertSame('', $accessor($specification));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool, mixed}>
     */
    public static function definesDefaultPresenceProvider(): iterable
    {
        yield 'default present with value' => [['default' => 'fallback alt', 'required' => false], true, 'fallback alt'];
        // Load-bearing: buildInputs() keys on array_key_exists('default', ...), so an explicit null default
        // is distinct from an absent one. A regression to a null-coalescing check would collapse the two.
        yield 'no default key' => [['required' => false], false, null];
        yield 'explicit null default' => [['default' => null, 'required' => false], true, null];
    }

    /**
     * @return iterable<string, array{array<string, mixed>, bool}>
     */
    public static function definesRequiredFlagProvider(): iterable
    {
        yield 'carries required:true' => [['required' => true], true];
        yield 'carries required:false' => [['required' => false], false];
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

    /**
     * @return iterable<string, array{mixed, mixed, callable(BindingSpecification): string}>
     */
    public static function narrowsNonStringToEmptyStringProvider(): iterable
    {
        yield 'type narrowed from non-string' => [42, 'label', static fn (BindingSpecification $specification): string => $specification->type()];
        yield 'label narrowed from non-string' => ['media-gallery', false, static fn (BindingSpecification $specification): string => $specification->label()];
    }
}
