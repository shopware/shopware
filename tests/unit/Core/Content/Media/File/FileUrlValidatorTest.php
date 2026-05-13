<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileUrlValidator;

/**
 * @internal
 */
#[CoversClass(FileUrlValidator::class)]
class FileUrlValidatorTest extends TestCase
{
    #[DataProvider('fileSourceProvider')]
    public function testIsValid(string $source, bool $expectedResult): void
    {
        $validator = new FileUrlValidator();

        static::assertSame($expectedResult, $validator->isValid($source));
    }

    /**
     * @return iterable<string, array<int, string|bool>>
     */
    public static function fileSourceProvider(): iterable
    {
        yield 'reserved IPv4' => ['https://127.0.0.1', false];
        yield 'reserved IPv4 hostname' => ['https://localhost', false];
        yield 'converted reserved IPv4' => ['https://0:0:0:0:0:FFFF:7F00:0001', false];
        yield 'reserved IPv4 mapped to IPv6' => ['https://[0:0:0:0:0:FFFF:127.0.0.1]', false];
        yield 'reserved IPv6' => ['https://FE80::', false];
        yield 'private IPv4' => ['https://192.168.0.0', false];
        yield 'converted private IPv4' => ['https://0:0:0:0:0:FFFF:C0A8:0000', false];
        yield 'private IPv4 mapped to IPv6' => ['https://[0:0:0:0:0:FFFF:192.168.0.0]', false];
        yield 'invalid IPv4' => ['https://378.0.0.1', false];
        yield 'valid IPv4' => ['https://8.8.8.8', true];
        yield 'invalid IPv6 format' => ['https://fe80:2030:31:24', false];
        yield 'valid IPv6' => ['https://[2000:db8::8a2e:370:7334]', true];
        yield 'valid IPv6 with port' => ['https://[2000:db8::8a2e:370:7334]:123', true];
        yield 'private IPv6, valid format' => ['https://[FC00::]', false];
        yield 'reserved IPv6, valid format' => ['https://[FE80::]', false];
    }
}
