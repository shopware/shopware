<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ORM\Search\Term;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ORM\Search\Term\Tokenizer;

class TokenizerTest extends TestCase
{
    /**
     * @dataProvider cases
     *
     * @param string $term
     * @param array  $expected
     */
    public function testInterpreter(string $term, array $expected)
    {
        $interpreter = new Tokenizer();
        $tokens = $interpreter->tokenize($term);
        static::assertSame($expected, $tokens);
    }

    public function cases()
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
        ];
    }
}
