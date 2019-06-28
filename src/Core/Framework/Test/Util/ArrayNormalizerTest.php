<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Util;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\ArrayNormalizer;

class ArrayNormalizerTest extends TestCase
{
    /**
     * @dataProvider provideTestData
     */
    public function testFlattening(array $nested, array $flattened): void
    {
        static::assertSame($flattened, ArrayNormalizer::flatten($nested));
    }

    /**
     * @dataProvider provideTestData
     */
    public function testExpanding(array $nested, array $flattened): void
    {
        static::assertSame($nested, ArrayNormalizer::expand($flattened));
    }

    public function provideTestData(): array
    {
        return [
            [
                [ //nested
                    'firstName' => 'Foo',
                    'lastName' => 'Bar',
                    'billingAddress' => [
                        'street' => 'Foostreet',
                    ],
                ],
                [ //flattened
                    'firstName' => 'Foo',
                    'lastName' => 'Bar',
                    'billingAddress.street' => 'Foostreet',
                ],
            ],
        ];
    }
}
