<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Term;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;

class TokenizerTest extends TestCase
{
    /**
     * @dataProvider cases
     */
    public function testInterpreter(string $term, array $expected): void
    {
        $tokens = (new Tokenizer())->tokenize($term);
        static::assertSame($expected, $tokens);
    }

    public function cases(): array
    {
        return [
            [
                'shopware AG',
                ['shopware'],
            ],
            [
                'Österreicher Essen',
                ['österreicher', 'essen'],
            ],
            [
                '!Example"§$%``=)(/&%%$§""!',
                ['example'],
            ],
            [
                'Synergistic Copper DM-10000 FaceMaster',
                ['synergistic', 'copper', 'dm-10000', 'facemaster'],
            ],
        ];
    }
}
