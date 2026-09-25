<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Filter\PlainTextFilter;
use Shopware\Core\Framework\Log\Package;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PlainTextFilter::class)]
class PlainTextFilterTest extends TestCase
{
    #[DataProvider('htmlProvider')]
    public function testToPlainText(?string $html, string $expected): void
    {
        static::assertSame($expected, (new PlainTextFilter())->toPlainText($html));
    }

    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function htmlProvider(): iterable
    {
        yield 'paragraph boundary becomes a space' => [
            '<p>This is a short sentence. This is the second short sentence.</p><p>Does this improve the quality of the product description? I do not know.</p>',
            'This is a short sentence. This is the second short sentence. Does this improve the quality of the product description? I do not know.',
        ];
        yield 'block tags are separated' => [
            '<div>Div.</div><h2>Heading</h2><ul><li>Red</li><li>Green</li></ul><table><tr><th>Size</th><td>XL</td></tr></table>',
            'Div. Heading Red Green Size XL',
        ];
        yield 'line breaks in all notations become a space' => [
            'One<br>Two<br/>Three<br />Four<BR>Five',
            'One Two Three Four Five',
        ];
        yield 'block tags with attributes are separated' => [
            '<p class="lead" style="color: red;">First.</p><div data-foo="bar">Second.</div>',
            'First. Second.',
        ];
        yield 'block tags with a closing angle bracket in an attribute value are removed completely' => [
            '<p title="a>b">First.</p><p>Second.</p>',
            'First. Second.',
        ];
        yield 'nested block tags result in a single space' => [
            '<div><div><p>First.</p></div></div><div><p><strong>Second.</strong></p></div>',
            'First. Second.',
        ];
        yield 'inline tags do not insert a space inside a word' => [
            'Sh<strong>op</strong><em>ware</em> is <a href="#">great</a><span>!</span>',
            'Shopware is great!',
        ];
        yield 'tags that only start like a block tag are treated as inline' => [
            '<pre>Code</pre><progress>50</progress><b>bold</b><bdi>text</bdi>',
            'Code 50boldtext',
        ];
        yield 'existing whitespace is collapsed and trimmed' => [
            "  <p>First.</p>\n\n   <p>Second.\t\tThird.</p>\r\n  ",
            'First. Second. Third.',
        ];
        yield 'multibyte text is kept intact' => [
            '<p>Größe: 42 €</p><p>日本語</p>',
            'Größe: 42 € 日本語',
        ];
        yield 'HTML entities stay encoded' => [
            '<p>&lt;script&gt;alert(1)&lt;/script&gt;</p><p>&amp;&nbsp;more</p>',
            '&lt;script&gt;alert(1)&lt;/script&gt; &amp;&nbsp;more',
        ];
        yield 'plain text is kept as is' => [
            'Just text.',
            'Just text.',
        ];
        yield 'markup without text results in an empty string' => [
            '<p></p><br><div> </div>',
            '',
        ];
        yield 'null results in an empty string' => [
            null,
            '',
        ];
    }

    public function testFilterIsRegisteredAsTwigFilter(): void
    {
        $twig = new Environment(new ArrayLoader([
            'test' => '{{ description|sw_plain_text }}',
        ]));
        $twig->addExtension(new PlainTextFilter());

        static::assertSame(
            'First. Second &amp;lt;b&amp;gt;',
            $twig->render('test', ['description' => '<p>First.</p><p>Second &lt;b&gt;</p>']),
            'The filter result must not be marked as safe, so it is escaped like the result of striptags'
        );
    }
}
