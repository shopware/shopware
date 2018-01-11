<?php declare(strict_types=1);

namespace Shopware\Api\Test\Entity\Search\Term;

use PHPUnit\Framework\TestCase;
use Shopware\Api\Entity\Search\Term\Tokenizer;

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
        $this->assertSame($expected, $tokens);
    }

    public function cases()
    {
        return [
            [
                'shopware AG',
                ['shopware', 'ag'],
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
