<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\ArrayNormalizer;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\Util\ArrayNormalizer
 */
class ArrayNormalizerTest extends TestCase
{
    /**
     * @dataProvider provideTestData
     *
     * @param array<string, mixed> $nested
     * @param array<string, string> $flattened
     */
    public function testFlattening(array $nested, array $flattened): void
    {
        static::assertSame($flattened, ArrayNormalizer::flatten($nested));
    }

    /**
     * @dataProvider provideTestData
     *
     * @param array<string, mixed> $nested
     * @param array<string, string> $flattened
     */
    public function testExpanding(array $nested, array $flattened): void
    {
        static::assertSame($nested, ArrayNormalizer::expand($flattened));
    }

    /**
     * @return array<mixed>[][]
     */
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
