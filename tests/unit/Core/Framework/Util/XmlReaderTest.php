<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Util\XmlReader;

/**
 * @internal
 */
#[CoversClass(XmlReader::class)]
class XmlReaderTest extends TestCase
{
    #[DataProvider('phpizeTestCases')]
    public function testPhpize(mixed $expected, string $value): void
    {
        static::assertSame($expected, XmlReader::phpize($value));
    }

    public static function phpizeTestCases(): \Generator
    {
        yield 'numeric string values' => [
            '100',
            '"100"',
        ];

        yield 'json array' => [
            ['value1', 'value2'],
            '["value1", "value2"]',
        ];

        yield 'json object' => [
            ['property' => 'value'],
            '{ "property": "value" }',
        ];

        // the original symfony test cases for XmlUtils::phpize: https://github.com/symfony/symfony/blob/1317e39c9a92e409ef57ff8fe918e90776f7c05e/src/Symfony/Component/Config/Tests/Util/XmlUtilsTest.php#L153
        yield from [
            ['', ''],
            [null, 'null'],
            [true, 'true'],
            [false, 'false'],
            [null, 'Null'],
            [true, 'True'],
            [false, 'False'],
            [0, '0'],
            [1, '1'],
            [-1, '-1'],
            [0777, '0777'],
            [255, '0xFF'],
            [100.0, '1e2'],
            [-120.0, '-1.2E2'],
            [-10100.1, '-10100.1'],
            ['-10,100.1', '-10,100.1'],
            ['1234 5678 9101 1121 3141', '1234 5678 9101 1121 3141'],
            ['1,2,3,4', '1,2,3,4'],
            ['11,22,33,44', '11,22,33,44'],
            ['11,222,333,4', '11,222,333,4'],
            ['1,222,333,444', '1,222,333,444'],
            ['11,222,333,444', '11,222,333,444'],
            ['111,222,333,444', '111,222,333,444'],
            ['1111,2222,3333,4444,5555', '1111,2222,3333,4444,5555'],
            ['foo', 'foo'],
            [6, '0b0110'],
            [-511, '-0777'],
            ['0877', '0877'],
        ];
    }
}
