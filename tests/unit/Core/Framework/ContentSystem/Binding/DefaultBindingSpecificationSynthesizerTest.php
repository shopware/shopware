<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\DefaultBindingSpecificationSynthesizer;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DefaultBindingSpecificationSynthesizer::class)]
class DefaultBindingSpecificationSynthesizerTest extends TestCase
{
    private const PATH = '/types/media/image.yaml';

    #[TestDox('synthesizes the default specification with the type name as id and label and no inputs key')]
    public function testSynthesizesDefaultSpecificationShape(): void
    {
        $synthesizer = new DefaultBindingSpecificationSynthesizer();

        $result = $synthesizer->synthesize(
            ['properties' => ['media' => ['type' => 'Shopware\\Core\\Content\\Media\\MediaEntity', 'resolvedBy' => 'mediaId']]],
            'Sw:Media:Image',
            self::PATH,
        );

        static::assertNotNull($result);
        static::assertSame('Sw:Media:Image', $result['type']);
        static::assertSame('Sw:Media:Image', $result['label']);
        static::assertArrayNotHasKey('inputs', $result);
    }

    #[TestDox('uses meta.label as the label when present')]
    public function testUsesMetaLabelWhenPresent(): void
    {
        $synthesizer = new DefaultBindingSpecificationSynthesizer();

        $result = $synthesizer->synthesize(
            [
                'meta' => ['label' => 'Image'],
                'properties' => ['media' => ['type' => 'Shopware\\Core\\Content\\Media\\MediaEntity', 'resolvedBy' => 'mediaId']],
            ],
            'Sw:Media:Image',
            self::PATH,
        );

        static::assertNotNull($result);
        static::assertSame('Image', $result['label']);
    }

    #[TestDox('synthesizes one resolves entry per resolvedBy property, keyed by property key with the authored value verbatim')]
    public function testSynthesizesOneResolvesEntryPerResolvedByProperty(): void
    {
        $synthesizer = new DefaultBindingSpecificationSynthesizer();

        $result = $synthesizer->synthesize(
            [
                'properties' => [
                    'media' => ['type' => 'Shopware\\Core\\Content\\Media\\MediaEntity', 'resolvedBy' => 'mediaId'],
                    'thumbnail' => ['type' => 'Shopware\\Core\\Content\\Media\\MediaEntity', 'resolvedBy' => 'thumbnailId'],
                ],
            ],
            'Sw:Media:Image',
            self::PATH,
        );

        static::assertNotNull($result);
        static::assertSame(
            ['media' => 'mediaId', 'thumbnail' => 'thumbnailId'],
            $result['resolves'],
        );
    }

    #[TestDox('carries a non-bare-string resolvedBy value through to the resolves entry verbatim')]
    public function testCarriesMapFormResolvedByValueVerbatim(): void
    {
        $synthesizer = new DefaultBindingSpecificationSynthesizer();

        $resolvedBy = ['entity' => ['property' => 'mediaId']];

        $result = $synthesizer->synthesize(
            ['properties' => ['media' => ['type' => 'Shopware\\Core\\Content\\Media\\MediaEntity', 'resolvedBy' => $resolvedBy]]],
            'Sw:Media:Image',
            self::PATH,
        );

        static::assertNotNull($result);
        static::assertSame($resolvedBy, $result['resolves']['media']);
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('returnsNullProvider')]
    #[TestDox('returns null when $_dataName')]
    public function testReturnsNull(array $data): void
    {
        $synthesizer = new DefaultBindingSpecificationSynthesizer();

        static::assertNull($synthesizer->synthesize($data, 'Sw:Media:Image', self::PATH));
    }

    #[TestDox('performs no collision check when no string storage key can be extracted from a map-form resolvedBy value')]
    public function testNoCollisionCheckWhenStorageKeyIsNotExtractable(): void
    {
        $synthesizer = new DefaultBindingSpecificationSynthesizer();

        $result = $synthesizer->synthesize(
            [
                'properties' => [
                    // The single-key form's "property" sub-value is not a string, so no storage key is
                    // extractable; this must not throw even though "mediaId" is also a declared property below.
                    'media' => ['type' => 'Shopware\\Core\\Content\\Media\\MediaEntity', 'resolvedBy' => ['entity' => ['property' => 123]]],
                    'mediaId' => ['type' => 'string'],
                ],
            ],
            'Sw:Media:Image',
            self::PATH,
        );

        static::assertNotNull($result);
        static::assertSame(['entity' => ['property' => 123]], $result['resolves']['media']);
    }

    #[TestDox('mints an id at exactly the maximum length of 255 characters without throwing')]
    public function testMintsIdAtExactlyMaxLength(): void
    {
        $synthesizer = new DefaultBindingSpecificationSynthesizer();
        $type = str_repeat('a', 255);

        $result = $synthesizer->synthesize(
            ['properties' => ['media' => ['type' => 'Shopware\\Core\\Content\\Media\\MediaEntity', 'resolvedBy' => 'mediaId']]],
            $type,
            self::PATH,
        );

        static::assertNotNull($result);
        static::assertSame($type, $result['type']);
    }

    #[TestDox('throws a load-failed error naming the file when a property entry is not a map')]
    public function testThrowsOnNonArrayPropertyValue(): void
    {
        $synthesizer = new DefaultBindingSpecificationSynthesizer();

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationLoadFailed(
            self::PATH,
            'property "media" must be a map, got string',
        ));

        $synthesizer->synthesize(['properties' => ['media' => 'not-a-map']], 'Sw:Media:Image', self::PATH);
    }

    #[TestDox('throws a load-failed error naming the file when a resolvedBy property has no declared type')]
    public function testThrowsWhenResolvedByPropertyHasMissingType(): void
    {
        $synthesizer = new DefaultBindingSpecificationSynthesizer();

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationLoadFailed(
            self::PATH,
            'property "media" declares "resolvedBy" but its "type" is missing or not a string; resolvedBy is only valid on a declared reference (FQCN) property',
        ));

        $synthesizer->synthesize(['properties' => ['media' => ['resolvedBy' => 'mediaId']]], 'Sw:Media:Image', self::PATH);
    }

    #[TestDox('throws a load-failed error naming the file when a resolvedBy property declares a non-string (union) type')]
    public function testThrowsWhenResolvedByPropertyHasNonStringType(): void
    {
        $synthesizer = new DefaultBindingSpecificationSynthesizer();

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationLoadFailed(
            self::PATH,
            'property "media" declares "resolvedBy" but its "type" is missing or not a string; resolvedBy is only valid on a declared reference (FQCN) property',
        ));

        $synthesizer->synthesize(
            ['properties' => ['media' => ['type' => ['string', 'integer'], 'resolvedBy' => 'mediaId']]],
            'Sw:Media:Image',
            self::PATH,
        );
    }

    #[DataProvider('throwsOnNonReferencePropertyProvider')]
    #[TestDox('throws a load-failed error naming the file when resolvedBy is declared on a $_dataName property')]
    public function testThrowsWhenResolvedByOnNonReferenceProperty(string $type): void
    {
        $synthesizer = new DefaultBindingSpecificationSynthesizer();

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationLoadFailed(
            self::PATH,
            \sprintf('property "media" declares "resolvedBy" but its type "%s" is not a reference (FQCN) property; resolvedBy is only valid on a reference property', $type),
        ));

        $synthesizer->synthesize(['properties' => ['media' => ['type' => $type, 'resolvedBy' => 'mediaId']]], 'Sw:Media:Image', self::PATH);
    }

    /**
     * @param string|array<string, mixed> $resolvedBy
     */
    #[DataProvider('throwsOnCollidingStorageKeyProvider')]
    #[TestDox('throws a load-failed error naming the file when a colliding storage key comes from the $_dataName')]
    public function testThrowsOnCollidingStorageKey(string|array $resolvedBy): void
    {
        $synthesizer = new DefaultBindingSpecificationSynthesizer();

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationLoadFailed(
            self::PATH,
            'the resolvedBy storage key "mediaId" of property "media" collides with a declared property key of the same name; choose a different storage key',
        ));

        $synthesizer->synthesize(
            [
                'properties' => [
                    'media' => ['type' => 'Shopware\\Core\\Content\\Media\\MediaEntity', 'resolvedBy' => $resolvedBy],
                    'mediaId' => ['type' => 'string'],
                ],
            ],
            'Sw:Media:Image',
            self::PATH,
        );
    }

    #[TestDox('throws a load-failed error naming the file when two resolvedBy properties name the same storage key')]
    public function testThrowsWhenTwoResolvedByPropertiesNameSameStorageKey(): void
    {
        $synthesizer = new DefaultBindingSpecificationSynthesizer();

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationLoadFailed(
            self::PATH,
            'properties "media" and "thumbnail" both declare resolvedBy with the storage key "assetId"; two resolvedBy properties of one type must not name the same storage key',
        ));

        $synthesizer->synthesize(
            [
                'properties' => [
                    'media' => ['type' => 'Shopware\\Core\\Content\\Media\\MediaEntity', 'resolvedBy' => 'assetId'],
                    'thumbnail' => ['type' => 'Shopware\\Core\\Content\\Media\\MediaEntity', 'resolvedBy' => 'assetId'],
                ],
            ],
            'Sw:Media:Image',
            self::PATH,
        );
    }

    #[TestDox('throws a load-failed error when the minted id exceeds the maximum length of 255 characters')]
    public function testThrowsWhenMintedIdExceedsMaxLength(): void
    {
        $synthesizer = new DefaultBindingSpecificationSynthesizer();
        $type = str_repeat('a', 256);

        $this->expectExceptionObject(ContentSystemException::bindingSpecificationLoadFailed(
            self::PATH,
            \sprintf('the synthesized default id "%s" (the element type name) exceeds the maximum length of %d characters', $type, 255),
        ));

        $synthesizer->synthesize(
            ['properties' => ['media' => ['type' => 'Shopware\\Core\\Content\\Media\\MediaEntity', 'resolvedBy' => 'mediaId']]],
            $type,
            self::PATH,
        );
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function returnsNullProvider(): iterable
    {
        yield 'no property carries resolvedBy' => [['properties' => ['media' => ['type' => 'Shopware\\Core\\Content\\Media\\MediaEntity']]]];
        yield 'no properties declared' => [['meta' => ['label' => 'Image']]];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function throwsOnNonReferencePropertyProvider(): iterable
    {
        yield 'string' => ['string'];
        yield 'integer' => ['integer'];
        yield 'number' => ['number'];
        yield 'boolean' => ['boolean'];
        yield 'object' => ['object'];
    }

    /**
     * @return iterable<string, array{string|array<string, mixed>}>
     */
    public static function throwsOnCollidingStorageKeyProvider(): iterable
    {
        yield 'declared property key form' => ['mediaId'];
        yield 'single-key loader form' => [['entity' => ['property' => 'mediaId']]];
        yield 'canonical loader form' => [['loader' => 'entity', 'config' => ['entity' => 'media', 'property' => 'mediaId']]];
    }
}
