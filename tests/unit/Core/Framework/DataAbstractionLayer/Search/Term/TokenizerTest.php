<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Term;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\HtmlSanitizer;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Tokenizer::class)]
class TokenizerTest extends TestCase
{
    /**
     * @param string[] $expected
     * @param string[]|null $preservedChars
     */
    #[DataProvider('cases')]
    public function testInterpreter(string $term, array $expected, ?array $preservedChars = null): void
    {
        $tokens = (new Tokenizer(new HtmlSanitizer(cacheEnabled: false), 2, $preservedChars ?? []))->tokenize($term, 2);
        static::assertSame($expected, $tokens);
    }

    /**
     * @return iterable<string, array{0: string, 1: string[], 2?: string[]}>
     */
    public static function cases(): iterable
    {
        yield 'empty with space' => [
            '    ',
            [],
        ];

        yield 'text with space' => [
            'shopware AG',
            ['shopware', 'ag'],
        ];

        yield 'text with spaces' => [
            'test a thing',
            ['test', 'thing'],
        ];

        yield 'text with umlats' => [
            'Österreicher Essen',
            ['österreicher', 'essen'],
        ];

        yield 'text with special chars' => [
            '!Example"§$%``=)(/\&%%$§""!',
            ['example'],
        ];

        yield 'text with allowed chars' => [
            'Synergistic Copper DM-10000 FaceMaster',
            ['synergistic', 'copper', 'dm-10000', 'facemaster'],
            ['-'],
        ];

        yield 'text with not allowed chars' => [
            'Synergistic Copper DM.10000 FaceMaster',
            ['synergistic', 'copper', 'dm', '10000', 'facemaster'],
        ];

        yield 'text with custom allowed char' => [
            'Synergistic Copper DM.10000 Face@Master',
            ['synergistic', 'copper', 'dm.10000', 'face', 'master'],
            ['.'],
        ];

        yield 'text with multiple allowed chars' => [
            'Synergistic Copper DM.10000 Face@Master',
            ['synergistic', 'copper', 'dm.10000', 'face@master'],
            ['.', '@'],
        ];

        yield 'text with allowed chars "/"' => [
            '0000/0440',
            ['0000/0440'],
            ['/'],
        ];

        yield 'stray "<" does not swallow the rest of the term' => [
            'I <3 Kisses',
            ['kisses'],
        ];

        yield 'a tag still separates the words around it' => [
            'foo<b>bar</b>',
            ['foo', 'bar'],
        ];

        yield 'script content is dropped instead of tokenized' => [
            '<script>alert(1)</script>shoes',
            ['shoes'],
        ];

        yield 'entities are decoded before tags are stripped' => [
            '&lt;b&gt;bold&lt;/b&gt; text',
            ['bold', 'text'],
        ];

        yield '"<" before a digit stays a token boundary' => [
            'size<10 ok',
            ['size', '10', 'ok'],
        ];
    }
}
