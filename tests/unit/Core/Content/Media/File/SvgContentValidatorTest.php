<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\AbstractFileContentValidator;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\File\SvgContentValidator;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AbstractFileContentValidator::class)]
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
        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
    <defs>
        <path id="shape" d="M0 0h10v10H0z"/>
    </defs>
    <use href="#shape"/>
</svg>
SVG);

        try {
            $this->validator->validate($file);
            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSupportsSvg(): void
    {
        $file = $this->createSvgFile('<svg xmlns="http://www.w3.org/2000/svg"></svg>');

        try {
            static::assertTrue($this->validator->supports($file));
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSupportsReturnsFalseForNonSvgFiles(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'txt');
        static::assertIsString($tempFile);

        file_put_contents($tempFile, 'plain text');
        $size = filesize($tempFile);
        static::assertIsInt($size);
        $file = new MediaFile($tempFile, 'text/plain', 'txt', $size);

        try {
            static::assertFalse($this->validator->supports($file));
        } finally {
            unlink($tempFile);
        }
    }

    public function testValidateIgnoresUnsupportedFileTypes(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'txt');
        static::assertIsString($tempFile);

        file_put_contents($tempFile, 'plain text');
        $size = filesize($tempFile);
        static::assertIsInt($size);
        $file = new MediaFile($tempFile, 'text/plain', 'txt', $size);

        try {
            $this->validator->validate($file);

            static::assertSame('txt', $file->getFileExtension());
        } finally {
            unlink($tempFile);
        }
    }

    #[DataProvider('unsafeSvgProvider')]
    public function testUnsafeSvgIsRejected(string $svgContent, string $messageAppendix): void
    {
        $file = $this->createSvgFile($svgContent);

        try {
            $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . $messageAppendix));

            $this->validator->validate($file);
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testInvalidSvgRootIsRejected(): void
    {
        $file = $this->createSvgFile('<?xml version="1.0" encoding="UTF-8"?><xml/>');

        try {
            $this->expectExceptionObject(MediaException::invalidFile('The file is not a valid SVG document.'));

            $this->validator->validate($file);
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testMalformedSvgIsRejected(): void
    {
        $file = $this->createSvgFile('<svg xmlns="http://www.w3.org/2000/svg"><g></svg>');

        try {
            $this->expectExceptionObject(MediaException::invalidFile('The file is not a valid SVG document.'));

            $this->validator->validate($file);
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithInvalidNamespaceIsRejected(): void
    {
        $file = $this->createSvgFile('<svg xmlns="https://example.com/svg"></svg>');

        try {
            $this->expectExceptionObject(MediaException::invalidFile('The file is not a valid SVG document.'));

            $this->validator->validate($file);
        } finally {
            unlink($file->getFileName());
        }
    }

    public static function unsafeSvgProvider(): \Generator
    {
        yield 'event handler attribute' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>
SVG,
            'Event handler attributes not allowed: onload' . \PHP_EOL . 'Attributes not allowed: onload',
        ];

        yield 'script element' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>
SVG,
            'Elements not allowed: script',
        ];

        yield 'style element with url reference' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><style>.a{fill:url(https://attacker.invalid/fill);}</style></svg>
SVG,
            'External style references not allowed: style',
        ];

        yield 'foreign object element' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body/></foreignObject></svg>
SVG,
            'Elements not allowed: foreignobject, body',
        ];

        yield 'external href' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><image href="https://attacker.invalid/x.png"/></svg>
SVG,
            'External references not allowed: href',
        ];

        yield 'xlink href with data uri' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4="/></svg>
SVG,
            'External references not allowed: xlink:href',
        ];

        yield 'fill with external url' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><rect fill="url(https://attacker.invalid/pattern)"/></svg>
SVG,
            'External style references not allowed: fill',
        ];

        yield 'stroke with data url' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h10" stroke="url(data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=)"/></svg>
SVG,
            'External style references not allowed: stroke',
        ];

        yield 'mask with external url' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><rect mask="url(https://attacker.invalid/mask)"/></svg>
SVG,
            'External style references not allowed: mask',
        ];

        yield 'clip-path with external url' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><rect clip-path="url(https://attacker.invalid/clip)"/></svg>
SVG,
            'External style references not allowed: clip-path',
        ];

        yield 'style element with @import rule' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><style>@import "https://attacker.invalid/evil.css";</style></svg>
SVG,
            'External style references not allowed: style',
        ];

        yield 'style attribute with @import rule' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><rect style="@import 'https://attacker.invalid/evil.css'"/></svg>
SVG,
            'External style references not allowed: style',
        ];

        yield 'animation element' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><animate attributeName="x" from="0" to="10" dur="1s"/></svg>
SVG,
            'Elements not allowed: animate' . \PHP_EOL . 'Attributes not allowed: attributename, from, to, dur',
        ];

        yield 'processing instruction' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet href="https://attacker.invalid/x.css" type="text/css"?>
<svg xmlns="http://www.w3.org/2000/svg"></svg>
SVG,
            'Node types not allowed: xml-stylesheet',
        ];

        yield 'doctype' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE svg>
<svg xmlns="http://www.w3.org/2000/svg"></svg>
SVG,
            'Node types not allowed: svg',
        ];
    }

    public function testSvgWithAllowedXlinkReferencePassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">
    <defs>
        <path id="shape" d="M0 0h10v10H0z"/>
    </defs>
    <use xlink:href="#shape"/>
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithSafeStyleElementPassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><style type="text/css">.a{fill:red;}</style><rect class="a" width="10" height="10"/></svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithSafeStyleAttributePassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><rect style="fill:red" width="10" height="10"/></svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithLocalUrlReferenceInAttributePassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg">
    <defs>
        <lineargradient id="grad"><stop offset="0" stop-color="red"/></lineargradient>
    </defs>
    <rect width="10" height="10" fill="url(#grad)"/>
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithLocalUrlReferenceInStyleElementPassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg">
    <defs>
        <lineargradient id="grad"><stop offset="0" stop-color="red"/></lineargradient>
    </defs>
    <style type="text/css">.a{fill:url(#grad);}</style>
    <rect class="a" width="10" height="10"/>
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    /**
     * Regression coverage for real-world plugin payment icons (Apple Pay, card,
     * PUI, SEPA) that previously broke after the strict SVG allowlist landed.
     */
    public function testRealWorldPaymentIconsPassValidation(): void
    {
        $applePay = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" display="none">
    <path d="M5 10h10v2H5z"/>
</svg>
SVG);

        $card = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
    <path clip-rule="evenodd" fill-rule="evenodd" d="M0 0h20v20H0z"/>
</svg>
SVG);

        try {
            $this->validator->validate($applePay);
            $this->validator->validate($card);

            static::assertSame('svg', $applePay->getFileExtension());
            static::assertSame('svg', $card->getFileExtension());
        } finally {
            unlink($applePay->getFileName());
            unlink($card->getFileName());
        }
    }

    public function testSvgWithSymbolAndMarkerPassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
    <defs>
        <symbol id="dot" viewBox="0 0 2 2"><circle cx="1" cy="1" r="1"/></symbol>
        <marker id="arrow" markerWidth="6" markerHeight="6" refX="3" refY="3" orient="auto" markerUnits="strokeWidth">
            <path d="M0 0L6 3L0 6z"/>
        </marker>
    </defs>
    <use href="#dot" x="0" y="0"/>
    <line x1="0" y1="10" x2="20" y2="10" stroke="black" marker-end="url(#arrow)"/>
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithImageReferencingLocalFragmentPassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
    <defs>
        <symbol id="icon" viewBox="0 0 1 1"><rect width="1" height="1"/></symbol>
    </defs>
    <image href="#icon" width="10" height="10"/>
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithAnchorAndLangAttributesPassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" lang="en" xml:lang="en" viewBox="0 0 20 20">
    <defs>
        <symbol id="dot"><circle cx="1" cy="1" r="1"/></symbol>
    </defs>
    <a href="#dot"><rect width="10" height="10"/></a>
    <a xlink:href="#dot"><circle cx="15" cy="5" r="3"/></a>
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    #[DataProvider('anchorElementBypassAttemptsProvider')]
    public function testAnchorElementDoesNotBypassReferenceChecks(string $svgContent, string $messageAppendix): void
    {
        $file = $this->createSvgFile($svgContent);

        try {
            $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . $messageAppendix));

            $this->validator->validate($file);
        } finally {
            unlink($file->getFileName());
        }
    }

    public static function anchorElementBypassAttemptsProvider(): \Generator
    {
        yield 'anchor with external href' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><a href="https://attacker.invalid"><rect width="10" height="10"/></a></svg>
SVG,
            'External references not allowed: href',
        ];

        yield 'anchor with javascript pseudo scheme' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><rect width="10" height="10"/></a></svg>
SVG,
            'External references not allowed: href',
        ];

        yield 'anchor with data uri' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><a xlink:href="data:image/svg+xml;base64,PHN2Zy8+"><rect width="10" height="10"/></a></svg>
SVG,
            'External references not allowed: xlink:href',
        ];
    }

    public function testSvgWithAriaAttributesPassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" role="img" aria-label="logo" aria-labelledby="t" aria-describedby="d" aria-hidden="false">
    <title id="t">Logo</title>
    <desc id="d">An accessible logo</desc>
    <rect width="10" height="10"/>
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithPresentationAttributesPassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
    <rect width="10" height="10"
          color="red"
          visibility="visible"
          overflow="hidden"
          pointer-events="none"
          shape-rendering="geometricPrecision"
          vector-effect="non-scaling-stroke"
          paint-order="stroke fill"
          transform-origin="center"
          stroke-miterlimit="4"
          text-rendering="optimizeLegibility"
          image-rendering="auto"
          color-interpolation="sRGB"
          color-interpolation-filters="linearRGB"/>
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    /**
     * The expanded attribute allowlist must not weaken the universal value checks.
     * Even on freshly allowed attributes, external url() refs and event handlers
     * must still be rejected.
     */
    #[DataProvider('newlyAllowedAttributesDoNotBypassValueChecksProvider')]
    public function testNewlyAllowedAttributesDoNotBypassValueChecks(string $svgContent, string $messageAppendix): void
    {
        $file = $this->createSvgFile($svgContent);

        try {
            $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . $messageAppendix));

            $this->validator->validate($file);
        } finally {
            unlink($file->getFileName());
        }
    }

    public static function newlyAllowedAttributesDoNotBypassValueChecksProvider(): \Generator
    {
        yield 'cursor with external url' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><rect cursor="url(https://attacker.invalid/cursor.png), auto"/></svg>
SVG,
            'External style references not allowed: cursor',
        ];

        yield 'filter with external url' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><rect filter="url(https://attacker.invalid/filter)"/></svg>
SVG,
            'External style references not allowed: filter',
        ];

        yield 'marker-end with external url' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><line x1="0" y1="0" x2="10" y2="0" stroke="black" marker-end="url(https://attacker.invalid/arrow)"/></svg>
SVG,
            'External style references not allowed: marker-end',
        ];

        yield 'event handler on newly allowlisted-capable element (image)' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><image href="#x" onload="alert(1)"/></svg>
SVG,
            'Event handler attributes not allowed: onload' . \PHP_EOL . 'Attributes not allowed: onload',
        ];

        yield 'image element with external href' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><image href="https://attacker.invalid/leak.png"/></svg>
SVG,
            'External references not allowed: href',
        ];
    }

    public function testMerchantCanExtendAllowlistViaConfiguration(): void
    {
        $validator = $this->createValidator(
            ['svg', 'image'],
            ['xmlns', 'href'],
            ['href'],
        );

        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg">
    <image href="#local-symbol"/>
</svg>
SVG);

        try {
            $validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testMerchantConfigurationIsNormalizedToLowercase(): void
    {
        $validator = $this->createValidator(
            ['SVG', 'DEFS', 'PATH', 'USE'],
            ['XMLNS', 'XMLNS:XLINK', 'VIEWBOX', 'ID', 'D', 'XLINK:HREF'],
            ['XLINK:HREF'],
        );

        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">
    <defs>
        <path id="shape" d="M0 0h10v10H0z"/>
    </defs>
    <use xlink:href="#shape"/>
</svg>
SVG);

        try {
            $validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    private function createSvgFile(string $content): MediaFile
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'svg');
        static::assertIsString($tempFile);

        file_put_contents($tempFile, $content);
        $size = filesize($tempFile);
        static::assertIsInt($size);

        return new MediaFile($tempFile, 'image/svg+xml', 'svg', $size);
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
