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

        yield 'script element with passive metadata prefix bound to svg namespace' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><rdf:script xmlns:rdf="http://www.w3.org/2000/svg">alert(1)</rdf:script></svg>
SVG,
            'Elements not allowed: script',
        ];

        yield 'event handler with passive metadata prefix bound to svg namespace' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><cc:rect xmlns:cc="http://www.w3.org/2000/svg" cc:onload="alert(1)" width="1" height="1" /></svg>
SVG,
            'Attributes not allowed: cc:onload',
        ];

        yield 'script element after metadata' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><metadata><json><![CDATA[{"fontFamily":"lg"}]]></json></metadata><script>alert(1)</script></svg>
SVG,
            'Elements not allowed: script',
        ];

        yield 'script element inside metadata' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><metadata><script>alert(1)</script></metadata></svg>
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

        yield 'image with svg data uri' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><image href="data:image/svg+xml;base64,PHN2Zz48L3N2Zz4="/></svg>
SVG,
            'External references not allowed: href',
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

        yield 'animation mutating href' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><animate attributeName="href" from="#shape" to="https://attacker.invalid/shape.svg" dur="1s"/></svg>
SVG,
            'Animated attributes not allowed: href',
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

    public function testSvgWithAdditionalNamespacesPassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<svg width="24" height="24" enable-background="new 0 0 175.748 38.786" overflow="visible" version="1.0" viewBox="0 0 24 24" xml:space="preserve" xmlns="http://www.w3.org/2000/svg" xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">

<path d="m8.0933 11.601c-0.096138 0.13019-0.25699 0.35651-0.35488 0.4887-0.049696 0.06722-0.13945 0.18927 0.1581 0.18927h1.5666s0.25249-0.34349 0.46416-0.63078c0.28791-0.39081 0.024911-1.204-1.0044-1.204h-4.0538l-0.70288 0.95499h3.8309c0.1934 0 0.19077 0.0736 0.096263 0.20179zm-1.1509 0.91356c-0.29755 0-0.2078-0.1223-0.1581-0.18952 0.09789-0.13219 0.26137-0.35614 0.35751-0.48632 0.094635-0.12818 0.097139-0.20179-0.096513-0.20179h-1.752l-1.4116 1.9185h3.4426c1.137 0 1.77-0.77336 1.9652-1.0407 0-1.25e-4 -2.0323-1.25e-4 -2.347-1.25e-4zm2.2395 1.0409h2.0195l0.7656-1.041-2.0193 2.5e-4c-6.259e-4 -1.25e-4 -0.76585 1.0407-0.76585 1.0407zm5.2106-3.1112-0.77461 1.0521h-0.90129l0.77423-1.0521h-2.0189l-1.3507 1.8348h4.9396l1.3503-1.8348zm-2.2898 3.1112h2.0188l0.76597-1.0407h-2.0188c-7.51e-4 -1.25e-4 -0.76597 1.0407-0.76597 1.0407zm-11.103-0.63028v0.22107h2.8125l0.16248-0.22107zm3.2773-0.41059h-3.2773v0.22082h3.1145zm-3.2773 1.0409h2.5112l0.16173-0.21982h-2.673zm19.036-0.40934h2.9637v-0.22107h-2.801zm-0.30106 0.40934h3.2648v-0.21982h-3.1032zm0.7656-1.0409-0.16236 0.22107h2.6616v-0.22107zm-2.6443-0.23559 1.3506-1.8348h-2.1382c-7.51e-4 0-1.3516 1.8348-1.3516 1.8348zm-2.3123 0.23559s-0.14759 0.20166-0.21931 0.2988c-0.25349 0.34249-0.02929 0.74206 0.79814 0.74206h3.2423l0.76597-1.0407h-4.5871z" fill="#758ca3" stroke-width=".12518"/>
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithKnownPublicDoctypePassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" standalone="no"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd" >
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0h10v10H0z"/></svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgDoctypeWithInternalSubsetIsRejected(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<?xml version="1.0" standalone="no"?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd" [
    <!ENTITY local "value">
]>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><path d="M0 0h10v10H0z"/></svg>
SVG);

        try {
            $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . 'Node types not allowed: svg'));

            $this->validator->validate($file);
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithPassiveMetadataDataAttributesAndFiltersPassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" xmlns:cc="http://creativecommons.org/ns#" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape" xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#" xmlns:sodipodi="http://sodipodi.sourceforge.net/DTD/sodipodi-0.dtd" viewBox="0 0 10 10" data-name="icon" focusable="false" inkscape:version="1.3" sodipodi:docname="icon.svg">
    <metadata><rdf:RDF><cc:Work rdf:about=""><dc:format>image/svg+xml</dc:format></cc:Work></rdf:RDF></metadata>
    <sodipodi:namedview pagecolor="#fff" bordercolor="#666" />
    <defs>
        <filter id="shadow" filterUnits="userSpaceOnUse"><feGaussianBlur in="SourceAlpha" stdDeviation="1" result="blur" /></filter>
    </defs>
    <rect width="10" height="10" filter="url(#shadow)" color-rendering="auto" />
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithInlineFontPassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
    <defs>
        <font id="icons" horiz-adv-x="1024">
            <font-face units-per-em="1024" panose-1="2 0 0 0 0 0 0 0 0 0" ascent="960" descent="-64" alphabetic="0" bbox="0 -64 1024 960" underline-thickness="50" underline-position="-100" unicode-range="U+E000-EFFF" x-height="500" cap-height="700" slope="0" />
            <missing-glyph horiz-adv-x="1024" />
            <glyph unicode="&#xe900;" glyph-name="check" d="M0 0h10v10H0z" />
            <hkern u1="A" u2="V" g1="left" g2="right" k="-80" />
            <vkern u1="A" u2="V" g1="top" g2="bottom" k="12" />
        </font>
    </defs>
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithAdditionalPassiveAttributesPassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10" t="1569683925316" title="Imported icon">
    <g>
        <path d="M0 0h10v10H0z" space="preserve" font-scale="contain" text="Logo" path="M0 0H10" />
        <text><tspan rotate="0 0 0">Hi</tspan></text>
    </g>
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testEditorSpecificAttributesWithKnownNamespacesPassValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" xmlns:bx="https://boxy-svg.com" xmlns:sketch="http://www.bohemiancoding.com/sketch/ns" viewBox="0 0 10 10">
    <g bx:origin="0.283554 0.499554" sketch:type="MSPage"><path d="M0 0h10v10H0z" /></g>
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testEditorSpecificAttributesWithUnknownNamespacesAreRejected(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" xmlns:bx="https://attacker.invalid/boxy" xmlns:sketch="https://attacker.invalid/sketch" viewBox="0 0 10 10">
    <g bx:origin="0.283554 0.499554" sketch:type="MSPage"><path d="M0 0h10v10H0z" /></g>
</svg>
SVG);

        try {
            $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . 'Attributes not allowed: bx:origin, sketch:type'));

            $this->validator->validate($file);
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithXmpMetadataPassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
    <metadata><?xpacket begin="" id="W5M0MpCehiHzreSzNTczkc9d"?>
        <x:xmpmeta xmlns:x="adobe:ns:meta/" x:xmptk="Adobe XMP Core">
            <rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">
                <rdf:Description rdf:about="" xmlns:ExtensisFontSense="http://www.extensis.com/meta/FontSense/">
                    <ExtensisFontSense:slug>
                        <rdf:Bag>
                            <rdf:li>
                                <rdf:Description>
                                    <ExtensisFontSense:Family>Arial Hebrew</ExtensisFontSense:Family>
                                    <ExtensisFontSense:PostScriptName>ArialHebrew</ExtensisFontSense:PostScriptName>
                                </rdf:Description>
                            </rdf:li>
                        </rdf:Bag>
                    </ExtensisFontSense:slug>
                </rdf:Description>
            </rdf:RDF>
        </x:xmpmeta>
    <?xpacket end="w"?></metadata>
    <path d="M0 0h10v10H0z" />
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithEmbeddedRasterImagePassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">
    <image width="5" height="5" xlink:href="data:image/png;base64,iVBORw0KGgo=" />
    <image width="5" height="5" x="5" href="data:image/jpeg;base64,/9j/4AAQSkZJRg==" />
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithJsonMetadataPassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
    <metadata>
        <json><![CDATA[
            {
                "fontFamily": "lg",
                "fontURL": "https://github.com/sachinchoolur/lightgallery.js",
                "description": "Font generated by IcoMoon."
            }
        ]]></json>
    </metadata>
    <path d="M0 0h10v10H0z" />
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgJsonOutsideMetadataIsRejected(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
    <json><![CDATA[{"fontFamily":"lg"}]]></json>
</svg>
SVG);

        try {
            $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . 'Elements not allowed: json'));

            $this->validator->validate($file);
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgFontSourceWithExternalReferenceIsRejected(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 10 10">
    <defs>
        <font-face-src><font-face-uri xlink:href="https://attacker.invalid/font.svg" /></font-face-src>
    </defs>
</svg>
SVG);

        try {
            $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . 'Elements not allowed: font-face-src, font-face-uri' . \PHP_EOL . 'External references not allowed: xlink:href'));

            $this->validator->validate($file);
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgWithSafeAnimationsPassesValidation(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 50 50">
    <circle cx="25" cy="25" r="1" stroke="currentColor" fill="none">
        <animate attributeName="r" begin="0s" dur="1.8s" values="1;20" calcMode="spline" keyTimes="0;1" keySplines="0.165,0.84,0.44,1" repeatCount="indefinite" />
        <animate attributeName="stroke-opacity" begin="0s" dur="1.8s" values="1;0" calcMode="spline" keyTimes="0;1" keySplines="0.3,0.61,0.355,1" repeatCount="indefinite" />
        <animate attributeName="fill" begin="0s" dur="1.8s" values="red;blue" calcMode="discrete" repeatCount="indefinite" />
    </circle>
    <rect x="10" y="13" width="4" height="5">
        <animate attributeName="height" attributeType="XML" from="5" to="21" begin="0s" dur="0.6s" repeatCount="indefinite" />
        <animate attributeName="y" attributeType="XML" values="13;5;13" begin="0s" dur="0.6s" repeatCount="indefinite" />
    </rect>
    <path d="M25 10a15 15 0 1 1 0 30" fill="remove">
        <animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="0.8s" calcMode="linear" additive="replace" accumulate="none" restart="always" repeatCount="indefinite" />
    </path>
</svg>
SVG);

        try {
            $this->validator->validate($file);

            static::assertSame('svg', $file->getFileExtension());
        } finally {
            unlink($file->getFileName());
        }
    }

    public function testSvgAnimationCannotMutateReferences(): void
    {
        $file = $this->createSvgFile(<<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">
    <defs><path id="shape" d="M0 0h10v10H0z" /></defs>
    <use href="#shape">
        <animate attributeName="href" values="#shape;https://attacker.invalid/shape.svg" dur="1s" />
    </use>
</svg>
SVG);

        try {
            $this->expectExceptionObject(MediaException::invalidFile('SVG files with active content are not allowed.' . \PHP_EOL . 'Animated attributes not allowed: href'));

            $this->validator->validate($file);
        } finally {
            unlink($file->getFileName());
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

        yield 'passive text path attribute with external url' => [
            <<< 'SVG'
<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0h10v10H0z" path="url(https://attacker.invalid/path)"/></svg>
SVG,
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
