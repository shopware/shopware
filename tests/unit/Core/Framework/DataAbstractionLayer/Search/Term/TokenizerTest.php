<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Search\Term;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;

/**
 * @internal
 */
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
        $tokens = (new Tokenizer(2, $preservedChars ?? []))->tokenize($term);
        static::assertSame($expected, $tokens);
    }

    /**
     * @return array<string, array<string|string[]>>
     */
    public static function cases(): array
    {
        return [
            'empty with space' => [
                '    ',
                [],
            ],
            'text with space' => [
                'shopware AG',
                ['shopware', 'ag'],
            ],
            'text with spaces' => [
                'test a thing',
                ['test', 'thing'],
            ],
            'text with umlats' => [
                'Österreicher Essen',
                ['österreicher', 'essen'],
            ],
            'text with special chars' => [
                '!Example"§$%``=)(/&%%$§""!',
                ['example'],
            ],
            'text with allowed chars' => [
                'Synergistic Copper DM-10000 FaceMaster',
                ['synergistic', 'copper', 'dm-10000', 'facemaster'],
                ['-'],
            ],
            'text with not allowed chars' => [
                'Synergistic Copper DM.10000 FaceMaster',
                ['synergistic', 'copper', 'dm', '10000', 'facemaster'],
            ],
            'text with custom allowed char' => [
                'Synergistic Copper DM.10000 Face@Master',
                ['synergistic', 'copper', 'dm.10000', 'face', 'master'],
                ['.'],
            ],
            'text with multiple allowed chars' => [
                'Synergistic Copper DM.10000 Face@Master',
                ['synergistic', 'copper', 'dm.10000', 'face@master'],
                ['.', '@'],
            ],
        ];
    }
}
