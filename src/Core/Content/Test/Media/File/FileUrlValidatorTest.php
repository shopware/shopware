<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\File\FileUrlValidator;

/**
 * @internal
 */
class FileUrlValidatorTest extends TestCase
{
    /**
     * @dataProvider fileSourceProvider
     */
    public function testIsValid(string $source, bool $expectedResult): void
    {
        $validator = new FileUrlValidator();

        static::assertEquals($expectedResult, $validator->isValid($source));
    }

    public static function fileSourceProvider(): array
    {
        return [
            'reserved IPv4' => ['https://127.0.0.1', false],
            'converted reserved IPv4' => ['https://0:0:0:0:0:FFFF:7F00:0001', false],
            'reserved IPv4 mapped to IPv6' => ['https://[0:0:0:0:0:FFFF:127.0.0.1]', false],
            'reserved IPv6' => ['https://FE80::', false],
            'private IPv4' => ['https://192.168.0.0', false],
            'converted private IPv4' => ['https://0:0:0:0:0:FFFF:C0A8:0000', false],
            'private IPv4 mapped to IPv6' => ['https://[0:0:0:0:0:FFFF:192.168.0.0]', false],
            'invalid IPv4' => ['https://378.0.0.1', false],
            'valid IPv4' => ['https://8.8.8.8', true],
            'invalid IPv6 format' => ['https://fe80:2030:31:24', false],
            'valid IPv6' => ['https://[2000:db8::8a2e:370:7334]', true],
            'valid IPv6 with port' => ['https://[2000:db8::8a2e:370:7334]:123', true],
            'private IPv6, valid format' => ['https://[FC00::]', false],
            'reserved IPv6, valid format' => ['https://[FE80::]', false],
        ];
    }
}
