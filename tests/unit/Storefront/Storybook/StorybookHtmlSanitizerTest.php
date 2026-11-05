<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Storybook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Storefront\Storybook\StorybookHtmlSanitizer;
use Shopware\Storefront\Storybook\StorybookHtmlSanitizerException;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(StorybookHtmlSanitizer::class)]
class StorybookHtmlSanitizerTest extends TestCase
{
    public function testSanitizesActiveContentUsingTheFixedAllowlist(): void
    {
        $result = (new StorybookHtmlSanitizer())->sanitize(
            '<script>alert(1)</script><div class="card" onclick="alert(1)" data-bs-toggle="modal" style="color:red">'
            . '<h2>Hello</h2><div aria-label="Preview controls" aria-hidden="true" role="region" aria-labelledby="controls-title">Controls</div>'
            . '<form><input name="host" type="text" value="safe"></form>'
            . '<img src="https://images.example/preview.png" alt="Preview"><img src="/preview.png">'
            . '<button type="submit" aria-expanded="false" aria-pressed="true">Action</button>'
            . '<a href="javascript:alert(1)">Unsafe link</a><a href="https://shop.example/page" aria-current="page">Safe link</a></div>',
            'https://shop.example',
        );

        static::assertStringNotContainsString('<script', $result);
        static::assertStringNotContainsString('onclick', $result);
        static::assertStringNotContainsString('data-bs-toggle', $result);
        static::assertStringNotContainsString('style=', $result);
        static::assertStringNotContainsString('name=', $result);
        static::assertStringNotContainsString('<form', $result);
        static::assertStringNotContainsString('javascript:', $result);
        static::assertStringContainsString('<div class="card">', $result);
        static::assertStringContainsString('<h2>Hello</h2>', $result);
        static::assertStringContainsString('<div aria-label="Preview controls" aria-hidden="true" role="region">Controls</div>', $result);
        static::assertStringNotContainsString('aria-labelledby', $result);
        static::assertStringContainsString('value="safe"', $result);
        static::assertStringContainsString('src="https://images.example/preview.png"', $result);
        static::assertStringContainsString('src="https://shop.example/preview.png"', $result);
        static::assertStringContainsString('<button type="submit" aria-expanded="false" aria-pressed="true">Action</button>', $result);
        static::assertStringContainsString('Action', $result);
        static::assertStringContainsString('href="https://shop.example/page"', $result);
        static::assertStringContainsString('aria-current="page"', $result);
    }

    public function testPreservesCommonSemanticMarkupAndRemovesUnsupportedSvg(): void
    {
        $result = (new StorybookHtmlSanitizer())->sanitize(
            '<nav><main><header><article><section><aside>Content</aside></section></article></header></main></nav>'
            . '<footer>Footer</footer><svg><path d="M0 0"/></svg>',
            'https://shop.example',
        );

        static::assertStringContainsString('<nav>', $result);
        static::assertStringContainsString('<main>', $result);
        static::assertStringContainsString('<header>', $result);
        static::assertStringContainsString('<article>', $result);
        static::assertStringContainsString('<section>', $result);
        static::assertStringContainsString('<aside>', $result);
        static::assertStringContainsString('<footer>', $result);
        static::assertStringNotContainsString('<svg', $result);
        static::assertStringNotContainsString('<path', $result);
    }

    public function testTreatsTwigDelimitersAsLiteralMarkup(): void
    {
        $result = (new StorybookHtmlSanitizer())->sanitize('<p>{{ 7 * 7 }}</p>{% include "secret" %}', 'https://shop.example');

        static::assertStringContainsString('{{ 7 * 7 }}', $result);
        static::assertStringContainsString('{% include "secret" %}', $result);
    }

    public function testWrapsHtmlSanitizerFailure(): void
    {
        $failure = new \RuntimeException('HTMLPurifier failed');
        $sanitizer = new class($failure) extends StorybookHtmlSanitizer {
            public function __construct(private readonly \Throwable $failure)
            {
            }

            protected function createHtmlSanitizer(string $requestOrigin): HtmlSanitizer
            {
                return new class($this->failure) extends HtmlSanitizer {
                    public function __construct(private readonly \Throwable $failure)
                    {
                    }

                    public function sanitize(string $text, ?array $options = [], bool $override = false, ?string $field = null): string
                    {
                        throw $this->failure;
                    }
                };
            }
        };

        try {
            $sanitizer->sanitize('<p>content</p>', 'https://shop.example');
            static::fail('Expected the HTML sanitizer failure to be wrapped.');
        } catch (StorybookHtmlSanitizerException $exception) {
            static::assertSame('Storybook HTML could not be sanitized.', $exception->getMessage());
            static::assertSame($failure, $exception->getPrevious());
        }
    }
}
