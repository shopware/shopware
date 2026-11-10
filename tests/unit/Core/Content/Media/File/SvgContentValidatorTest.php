<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\File\SvgContentValidator;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SvgContentValidator::class)]
class SvgContentValidatorTest extends TestCase
{
    private SvgContentValidator $validator;

    protected function setUp(): void
    {
        $this->validator = $this->createValidatorWithDefaultAllowlist();
    }

    public function testGetDecoratedThrowsException(): void
    {
        static::expectException(DecorationPatternException::class);

        $this->validator->getDecorated();
    }

    public function testValidSvgPassesValidation(): void
    {
        $file = $this->loadSvgFile('valid-svg-passes-validation');

        $this->validator->validate($file);
        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSupportsSvg(): void
    {
        $file = $this->loadSvgFile('supports-svg');

        static::assertTrue($this->validator->supports($file));
    }

    public function testSupportsReturnsFalseForNonSvgFiles(): void
    {
        $file = $this->loadFixtureFile('plain.txt', 'text/plain', 'txt');

        static::assertFalse($this->validator->supports($file));
    }

    public function testValidateIgnoresUnsupportedFileTypes(): void
    {
        $file = $this->loadFixtureFile('plain.txt', 'text/plain', 'txt');

        $this->validator->validate($file);

        static::assertSame('txt', $file->getFileExtension());
    }

    #[DataProvider('unsafeSvgProvider')]
    public function testUnsafeSvgIsRejected(string $fixture, string $messageAppendix): void
    {
        $file = $this->loadSvgFile($fixture);

        $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . $messageAppendix));

        $this->validator->validate($file);
    }

    public function testInvalidSvgRootIsRejected(): void
    {
        $file = $this->loadSvgFile('invalid-svg-root-is-rejected');

        $this->expectExceptionObject(MediaException::invalidFile('The file is not a valid SVG document.'));

        $this->validator->validate($file);
    }

    public function testMalformedSvgIsRejected(): void
    {
        $file = $this->loadSvgFile('malformed-svg-is-rejected');

        $this->expectExceptionObject(MediaException::invalidFile('The file is not a valid SVG document.'));

        $this->validator->validate($file);
    }

    public function testSvgWithInvalidNamespaceIsRejected(): void
    {
        $file = $this->loadSvgFile('svg-with-invalid-namespace-is-rejected');

        $this->expectExceptionObject(MediaException::invalidFile('The file is not a valid SVG document.'));

        $this->validator->validate($file);
    }

    public static function unsafeSvgProvider(): \Generator
    {
        yield 'event handler attribute' => [
            'event-handler-attribute',
            'Event handler attributes not allowed: onload' . \PHP_EOL . 'Attributes not allowed: onload',
        ];

        yield 'script element' => [
            'script-element',
            'Elements not allowed: script',
        ];

        yield 'script element with passive metadata prefix bound to svg namespace' => [
            'script-element-with-passive-metadata-prefix-bound-to-svg-namespace',
            'Elements not allowed: script',
        ];

        yield 'event handler with passive metadata prefix bound to svg namespace' => [
            'event-handler-with-passive-metadata-prefix-bound-to-svg-namespace',
            'Attributes not allowed: cc:onload',
        ];

        yield 'script element after metadata' => [
            'script-element-after-metadata',
            'Elements not allowed: script',
        ];

        yield 'script element inside metadata' => [
            'script-element-inside-metadata',
            'Elements not allowed: script',
        ];

        yield 'style element with url reference' => [
            'style-element-with-url-reference',
            'External style references not allowed: style',
        ];

        yield 'foreign object element' => [
            'foreign-object-element',
            'Elements not allowed: foreignobject, body',
        ];

        yield 'external href' => [
            'external-href',
            'External references not allowed: href',
        ];

        yield 'xlink href with data uri' => [
            'xlink-href-with-data-uri',
            'External references not allowed: xlink:href',
        ];

        yield 'image with svg data uri' => [
            'image-with-svg-data-uri',
            'External references not allowed: href',
        ];

        yield 'fill with external url' => [
            'fill-with-external-url',
            'External style references not allowed: fill',
        ];

        yield 'stroke with data url' => [
            'stroke-with-data-url',
            'External style references not allowed: stroke',
        ];

        yield 'mask with external url' => [
            'mask-with-external-url',
            'External style references not allowed: mask',
        ];

        yield 'clip-path with external url' => [
            'clip-path-with-external-url',
            'External style references not allowed: clip-path',
        ];

        yield 'style element with @import rule' => [
            'style-element-with-import-rule',
            'External style references not allowed: style',
        ];

        yield 'style attribute with @import rule' => [
            'style-attribute-with-import-rule',
            'External style references not allowed: style',
        ];

        yield 'animation mutating href' => [
            'animation-mutating-href',
            'Animated attributes not allowed: href',
        ];

        yield 'processing instruction' => [
            'processing-instruction',
            'Node types not allowed: xml-stylesheet',
        ];

        yield 'doctype' => [
            'doctype',
            'Node types not allowed: svg',
        ];
    }

    public function testSvgWithAllowedXlinkReferencePassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-allowed-xlink-reference-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgWithSafeStyleElementPassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-safe-style-element-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgWithSafeStyleAttributePassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-safe-style-attribute-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgWithLocalUrlReferenceInAttributePassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-local-url-reference-in-attribute-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgWithLocalUrlReferenceInStyleElementPassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-local-url-reference-in-style-element-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    /**
     * Regression coverage for real-world plugin payment icons (Apple Pay, card,
     * PUI, SEPA) that previously broke after the strict SVG allowlist landed.
     */
    public function testRealWorldPaymentIconsPassValidation(): void
    {
        $applePay = $this->loadSvgFile('real-world-payment-icons-pass-validation-apple-pay');

        $card = $this->loadSvgFile('real-world-payment-icons-pass-validation-card');

        $this->validator->validate($applePay);
        $this->validator->validate($card);

        static::assertSame('svg', $applePay->getFileExtension());
        static::assertSame('svg', $card->getFileExtension());
    }

    public function testSvgWithAdditionalNamespacesPassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-additional-namespaces-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgWithKnownPublicDoctypePassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-known-public-doctype-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgDoctypeWithInternalSubsetIsRejected(): void
    {
        $file = $this->loadSvgFile('svg-doctype-with-internal-subset-is-rejected');

        $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . 'Node types not allowed: svg'));

        $this->validator->validate($file);
    }

    public function testSvgWithPassiveMetadataDataAttributesAndFiltersPassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-passive-metadata-data-attributes-and-filters-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgWithInlineFontPassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-inline-font-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgWithAdditionalPassiveAttributesPassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-additional-passive-attributes-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testEditorSpecificAttributesWithKnownNamespacesPassValidation(): void
    {
        $file = $this->loadSvgFile('editor-specific-attributes-with-known-namespaces-pass-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testEditorSpecificAttributesWithUnknownNamespacesAreRejected(): void
    {
        $file = $this->loadSvgFile('editor-specific-attributes-with-unknown-namespaces-are-rejected');

        $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . 'Attributes not allowed: bx:origin, sketch:type'));

        $this->validator->validate($file);
    }

    public function testSvgWithXmpMetadataPassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-xmp-metadata-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgWithEmbeddedRasterImagePassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-embedded-raster-image-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgWithJsonMetadataPassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-json-metadata-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgJsonOutsideMetadataIsRejected(): void
    {
        $file = $this->loadSvgFile('svg-json-outside-metadata-is-rejected');

        $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . 'Elements not allowed: json'));

        $this->validator->validate($file);
    }

    public function testSvgFontSourceWithExternalReferenceIsRejected(): void
    {
        $file = $this->loadSvgFile('svg-font-source-with-external-reference-is-rejected');

        $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . 'Elements not allowed: font-face-src, font-face-uri' . \PHP_EOL . 'External references not allowed: xlink:href'));

        $this->validator->validate($file);
    }

    public function testSvgWithSafeAnimationsPassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-safe-animations-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgAnimationCannotMutateReferences(): void
    {
        $file = $this->loadSvgFile('svg-animation-cannot-mutate-references');

        $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . 'Animated attributes not allowed: href'));

        $this->validator->validate($file);
    }

    public function testSvgWithSymbolAndMarkerPassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-symbol-and-marker-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgWithImageReferencingLocalFragmentPassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-image-referencing-local-fragment-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgWithAnchorAndLangAttributesPassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-anchor-and-lang-attributes-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    #[DataProvider('anchorElementBypassAttemptsProvider')]
    public function testAnchorElementDoesNotBypassReferenceChecks(string $fixture, string $messageAppendix): void
    {
        $file = $this->loadSvgFile($fixture);

        $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . $messageAppendix));

        $this->validator->validate($file);
    }

    public static function anchorElementBypassAttemptsProvider(): \Generator
    {
        yield 'anchor with external href' => [
            'anchor-with-external-href',
            'External references not allowed: href',
        ];

        yield 'anchor with javascript pseudo scheme' => [
            'anchor-with-javascript-pseudo-scheme',
            'External references not allowed: href',
        ];

        yield 'anchor with data uri' => [
            'anchor-with-data-uri',
            'External references not allowed: xlink:href',
        ];
    }

    public function testSvgWithAriaAttributesPassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-aria-attributes-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testSvgWithPresentationAttributesPassesValidation(): void
    {
        $file = $this->loadSvgFile('svg-with-presentation-attributes-passes-validation');

        $this->validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    /**
     * The expanded attribute allowlist must not weaken the universal value checks.
     * Even on freshly allowed attributes, external url() refs and event handlers
     * must still be rejected.
     */
    #[DataProvider('newlyAllowedAttributesDoNotBypassValueChecksProvider')]
    public function testNewlyAllowedAttributesDoNotBypassValueChecks(string $fixture, string $messageAppendix): void
    {
        $file = $this->loadSvgFile($fixture);

        $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . $messageAppendix));

        $this->validator->validate($file);
    }

    public static function newlyAllowedAttributesDoNotBypassValueChecksProvider(): \Generator
    {
        yield 'cursor with external url' => [
            'cursor-with-external-url',
            'External style references not allowed: cursor',
        ];

        yield 'filter with external url' => [
            'filter-with-external-url',
            'External style references not allowed: filter',
        ];

        yield 'marker-end with external url' => [
            'marker-end-with-external-url',
            'External style references not allowed: marker-end',
        ];

        yield 'event handler on newly allowlisted-capable element (image)' => [
            'event-handler-on-newly-allowlisted-capable-element-image',
            'Event handler attributes not allowed: onload' . \PHP_EOL . 'Attributes not allowed: onload',
        ];

        yield 'image element with external href' => [
            'image-element-with-external-href',
            'External references not allowed: href',
        ];

        yield 'passive text path attribute with external url' => [
            'passive-text-path-attribute-with-external-url',
            'External style references not allowed: path',
        ];
    }

    public function testMerchantCanExtendAllowlistViaConfiguration(): void
    {
        $validator = $this->createValidator(
            ['svg', 'image'],
            ['xmlns', 'href'],
            ['href'],
        );

        $file = $this->loadSvgFile('merchant-can-extend-allowlist-via-configuration');

        $validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    public function testMerchantConfigurationIsNormalizedToLowercase(): void
    {
        $validator = $this->createValidator(
            ['SVG', 'DEFS', 'PATH', 'USE'],
            ['XMLNS', 'XMLNS:XLINK', 'VIEWBOX', 'ID', 'D', 'XLINK:HREF'],
            ['XLINK:HREF'],
        );

        $file = $this->loadSvgFile('merchant-configuration-is-normalized-to-lowercase');

        $validator->validate($file);

        static::assertSame('svg', $file->getFileExtension());
    }

    private function loadSvgFile(string $fixture): MediaFile
    {
        return $this->loadFixtureFile('svg/' . $fixture . '.svg', 'image/svg+xml', 'svg');
    }

    private function loadFixtureFile(string $fixture, string $mimeType, string $extension): MediaFile
    {
        $path = __DIR__ . '/_fixtures/' . $fixture;
        $size = filesize($path);
        static::assertIsInt($size);

        return new MediaFile($path, $mimeType, $extension, $size);
    }

    private function createValidatorWithDefaultAllowlist(): SvgContentValidator
    {
        return SvgValidatorTestDefaults::createValidator();
    }

    /**
     * @param list<string> $allowedElements
     * @param list<string> $allowedAttributes
     * @param list<string> $allowedReferenceAttributes
     */
    private function createValidator(
        array $allowedElements,
        array $allowedAttributes,
        array $allowedReferenceAttributes,
    ): SvgContentValidator {
        return new SvgContentValidator($allowedElements, $allowedAttributes, $allowedReferenceAttributes);
    }
}
