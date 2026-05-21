<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Security;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Ucp\Security\PrivateKeyEncryptor;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @internal
 */
#[CoversClass(PrivateKeyEncryptor::class)]
class PrivateKeyEncryptorTest extends TestCase
{
    public function testEncryptDecryptRoundtrip(): void
    {
        $encryptor = new PrivateKeyEncryptor('test-app-secret-must-be-long-enough');
        $kid = 'ucp_2026_abc123';
        $plain = "-----BEGIN PRIVATE KEY-----\nABCDEFG\n-----END PRIVATE KEY-----\n";

        $cipher = $encryptor->encrypt($plain, $kid);
        static::assertNotSame($plain, $cipher);
        $back = $encryptor->decrypt($cipher, $kid);
        static::assertSame($plain, $back);
    }

    public function testDecryptionFailsWithWrongKid(): void
    {
        $encryptor = new PrivateKeyEncryptor('shared-secret');
        $cipher = $encryptor->encrypt('payload', 'kid-1');

        $this->expectException(UcpException::class);
        $encryptor->decrypt($cipher, 'kid-2');
    }

    public function testDecryptionFailsWithRotatedSecret(): void
    {
        $a = new PrivateKeyEncryptor('secret-A');
        $cipher = $a->encrypt('payload', 'kid');

        $b = new PrivateKeyEncryptor('secret-B');

        $this->expectException(UcpException::class);
        $b->decrypt($cipher, 'kid');
    }

    public function testCiphertextIsUnpredictable(): void
    {
        $encryptor = new PrivateKeyEncryptor('s');
        $a = $encryptor->encrypt('same-input', 'k');
        $b = $encryptor->encrypt('same-input', 'k');
        static::assertNotSame($a, $b, 'IV-based encryption must produce different ciphertext on each call');
    }

    public function testReencryptMigratesFromOldToNewSecret(): void
    {
        $oldEncryptor = new PrivateKeyEncryptor('old');
        $cipher = $oldEncryptor->encrypt('payload', 'kid');

        $migrator = new PrivateKeyEncryptor('any');
        $newCipher = $migrator->reencrypt($cipher, 'kid', 'old', 'new');

        $newEncryptor = new PrivateKeyEncryptor('new');
        static::assertSame('payload', $newEncryptor->decrypt($newCipher, 'kid'));
    }
}
