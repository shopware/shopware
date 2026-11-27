<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDescriptionTeaserBuilder::class)]
class ProductDescriptionTeaserBuilderTest extends TestCase
{
    public function testStripsHtmlAndKeepsText(): void
    {
        static::assertSame(
            'Hello World',
            $this->builder()->build('<p style="color: red;">Hello <strong>World</strong></p>')
        );
    }

    public function testStripsHtmlWhenSanitizerIsDisabled(): void
    {
        $builder = new ProductDescriptionTeaserBuilder(new HtmlSanitizer(enabled: false));

        static::assertSame(
            'Test',
            $builder->build('<h2></h2><h1 class="' . str_repeat('padding', 100) . '">Test</h1>')
        );
    }

    public function testTruncatesToMaxLength(): void
    {
        $teaser = $this->builder()->build(str_repeat('a', 1000));

        static::assertNotNull($teaser);
        static::assertSame(512, mb_strlen($teaser));
    }

    public function testKeepsNull(): void
    {
        static::assertNull($this->builder()->build(null));
    }

    public function testKeepsEmptyString(): void
    {
        static::assertSame('', $this->builder()->build(''));
    }

    private function builder(): ProductDescriptionTeaserBuilder
    {
        return new ProductDescriptionTeaserBuilder(
            new HtmlSanitizer(null, false, [], [ProductDescriptionTeaserBuilder::TEASER_FIELD => ['sets' => []]])
        );
    }
}
