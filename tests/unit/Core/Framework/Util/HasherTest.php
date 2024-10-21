<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Util\UtilException;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Hasher::class)]
class HasherTest extends TestCase
{
    #[DataProvider('hashProvider')]
    public function testHash(string $data, ?string $algo, string $expectedHash): void
    {
        if ($algo === null) {
            $result = Hasher::hash($data);
        } else {
            $result = Hasher::hash($data, $algo);
        }

        static::assertSame($expectedHash, $result);
    }

    #[DataProvider('hashProvider')]
    public function testHashBinary(string $data, ?string $algo, string $expectedHash): void
    {
        if ($algo === null) {
            $result = Hasher::hashBinary($data);
        } else {
            $result = Hasher::hashBinary($data, $algo);
        }

        static::assertSame($expectedHash, bin2hex($result));
    }

    public static function hashProvider(): \Generator
    {
        yield 'default algo' => ['data' => 'foobar', 'algo' => null, 'expectedHash' => '3c9e102628997f44ac87b0b131c6992d'];

        yield 'md5 algo' => ['data' => 'foobar', 'algo' => 'md5', 'expectedHash' => '3858f62230ac3c915f300c664312c63f'];

        yield 'sha1 algo' => ['data' => 'foobar', 'algo' => 'sha1', 'expectedHash' => '8843d7f92416211de9ebb963ff4ce28125932878'];
    }

    #[DataProvider('hashFileProvider')]
    public function testHashFile(string $filename, ?string $algo, string $expectedHash): void
    {
        if ($algo === null) {
            $result = Hasher::hashFile($filename);
        } else {
            $result = Hasher::hashFile($filename, $algo);
        }

        static::assertSame($expectedHash, $result);
    }

    public function testHashFileThrowsWhenFileDoesNotExist(): void
    {
        static::expectExceptionObject(UtilException::couldNotHashFile('non-existing-file.txt'));
        // silence warning that is thrown by hash_file when file does not exist, as we want to test the exception
        @Hasher::hashFile('non-existing-file.txt');
    }

    public static function hashFileProvider(): \Generator
    {
        yield 'default algo' => ['filename' => __DIR__ . '/fixtures/test.txt', 'algo' => null, 'expectedHash' => '3c9e102628997f44ac87b0b131c6992d'];

        yield 'md5 algo' => ['filename' => __DIR__ . '/fixtures/test.txt', 'algo' => 'md5', 'expectedHash' => '3858f62230ac3c915f300c664312c63f'];

        yield 'sha1 algo' => ['filename' => __DIR__ . '/fixtures/test.txt', 'algo' => 'sha1', 'expectedHash' => '8843d7f92416211de9ebb963ff4ce28125932878'];
    }
}
