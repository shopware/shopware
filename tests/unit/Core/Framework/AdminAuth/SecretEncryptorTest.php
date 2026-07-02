<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\AdminAuth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\AdminAuth\AdminAuthException;
use Shopware\Core\Framework\AdminAuth\SecretEncryptor;

/**
 * @internal
 */
#[CoversClass(SecretEncryptor::class)]
#[CoversClass(AdminAuthException::class)]
class SecretEncryptorTest extends TestCase
{
    private SecretEncryptor $encryptor;

    protected function setUp(): void
    {
        $this->encryptor = new SecretEncryptor('the-application-secret');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function plaintextProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'short' => ['a'];
        yield 'simple' => ['hello world'];
        yield 'unicode' => ['héllo wörld — 你好 🔐'];
        yield 'binary-ish base32 secret' => ['JBSWY3DPEHPK3PXP'];
        yield 'long' => [str_repeat('A1b2C3d4-', 1000)];
        yield 'newlines and tabs' => ["line1\nline2\tend"];
    }

    #[DataProvider('plaintextProvider')]
    public function testRoundTrip(string $plaintext): void
    {
        $encrypted = $this->encryptor->encrypt($plaintext);

        static::assertSame($plaintext, $this->encryptor->decrypt($encrypted));
    }

    public function testCiphertextDiffersFromPlaintext(): void
    {
        $plaintext = 'super-secret-totp-seed';

        $encrypted = $this->encryptor->encrypt($plaintext);

        static::assertNotSame($plaintext, $encrypted);
        static::assertStringNotContainsString($plaintext, $encrypted);
    }

    public function testTwoEncryptionsOfSameInputDiffer(): void
    {
        $plaintext = 'identical-input';

        $first = $this->encryptor->encrypt($plaintext);
        $second = $this->encryptor->encrypt($plaintext);

        // Random per-value nonce must make the ciphertext non-deterministic.
        static::assertNotSame($first, $second);

        // But both must decrypt back to the original.
        static::assertSame($plaintext, $this->encryptor->decrypt($first));
        static::assertSame($plaintext, $this->encryptor->decrypt($second));
    }

    public function testDecryptGarbageThrows(): void
    {
        $this->expectExceptionObject(AdminAuthException::decryptionFailed());

        $this->encryptor->decrypt('this-is-not-valid-ciphertext');
    }

    public function testDecryptInvalidBase64Throws(): void
    {
        $this->expectExceptionObject(AdminAuthException::decryptionFailed());

        $this->encryptor->decrypt('!!! not base64 !!!');
    }

    public function testDecryptTooShortThrows(): void
    {
        $this->expectExceptionObject(AdminAuthException::decryptionFailed());

        // Valid base64 but shorter than nonce + tag, so it cannot be decrypted.
        $this->encryptor->decrypt(base64_encode('short'));
    }

    public function testDecryptWithWrongKeyThrows(): void
    {
        $encrypted = $this->encryptor->encrypt('payload');

        $otherEncryptor = new SecretEncryptor('a-completely-different-secret');

        $this->expectExceptionObject(AdminAuthException::decryptionFailed());

        $otherEncryptor->decrypt($encrypted);
    }

    public function testTamperedCiphertextThrows(): void
    {
        $encrypted = $this->encryptor->encrypt('payload that will be tampered with');

        $raw = base64_decode($encrypted, true);
        static::assertIsString($raw);

        // Flip a bit in the ciphertext region (after the 12-byte nonce).
        $tampered = substr($raw, 0, 15) . ($raw[15] ^ "\xff") . substr($raw, 16);
        $raw = $tampered;

        $this->expectExceptionObject(AdminAuthException::decryptionFailed());

        $this->encryptor->decrypt(base64_encode($raw));
    }
}
