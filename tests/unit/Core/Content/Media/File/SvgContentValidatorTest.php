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
    public function testUnsafeSvgIsRejected(string $svgContent): void
    {
        $file = $this->createSvgFile($svgContent);

        try {
            $this->expectException(MediaException::class);
            $this->expectExceptionMessage('SVG files with active content are not allowed.');

            $this->validator->validate($file);
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testInvalidSvgRootIsRejected(): void
    {
        $file = $this->createSvgFile('<?xml version="1.0" encoding="UTF-8"?><xml/>');

        try {
            $this->expectException(MediaException::class);
            $this->expectExceptionMessage('The file is not a valid SVG document.');

            $this->validator->validate($file);
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testMalformedSvgIsRejected(): void
    {
        $file = $this->createSvgFile('<svg xmlns="http://www.w3.org/2000/svg"><g></svg>');

        try {
            $this->expectException(MediaException::class);
            $this->expectExceptionMessage('The file is not a valid SVG document.');

            $this->validator->validate($file);
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithInvalidNamespaceIsRejected(): void
    {
        $file = $this->createSvgFile('<svg xmlns="https://example.com/svg"></svg>');

        try {
            $this->expectException(MediaException::class);
            $this->expectExceptionMessage('The file is not a valid SVG document.');

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
        ];

        yield 'script element' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>
SVG,
        ];

        yield 'style element with url reference' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><style>.a{fill:url(https://attacker.invalid/fill);}</style></svg>
SVG,
        ];

        yield 'foreign object element' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><foreignObject><body/></foreignObject></svg>
SVG,
        ];

        yield 'external href' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><image href="https://attacker.invalid/x.png"/></svg>
SVG,
        ];

        yield 'xlink href with data uri' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink"><use xlink:href="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4="/></svg>
SVG,
        ];

        yield 'fill with external url' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><rect fill="url(https://attacker.invalid/pattern)"/></svg>
SVG,
        ];

        yield 'stroke with data url' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h10" stroke="url(data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=)"/></svg>
SVG,
        ];

        yield 'mask with external url' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><rect mask="url(https://attacker.invalid/mask)"/></svg>
SVG,
        ];

        yield 'clip-path with external url' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><rect clip-path="url(https://attacker.invalid/clip)"/></svg>
SVG,
        ];

        yield 'style element with @import rule' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><style>@import "https://attacker.invalid/evil.css";</style></svg>
SVG,
        ];

        yield 'style attribute with @import rule' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><rect style="@import 'https://attacker.invalid/evil.css'"/></svg>
SVG,
        ];

        yield 'animation element' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><animate attributeName="x" from="0" to="10" dur="1s"/></svg>
SVG,
        ];

        yield 'processing instruction' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<?xml-stylesheet href="https://attacker.invalid/x.css" type="text/css"?>
<svg xmlns="http://www.w3.org/2000/svg"></svg>
SVG,
        ];

        yield 'doctype' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE svg>
<svg xmlns="http://www.w3.org/2000/svg"></svg>
SVG,
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
