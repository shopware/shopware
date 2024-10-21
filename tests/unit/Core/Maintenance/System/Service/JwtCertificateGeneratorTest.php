<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;

/**
 * @internal
 */
#[CoversClass(JwtCertificateGenerator::class)]
class JwtCertificateGeneratorTest extends TestCase
{
    private JwtCertificateGenerator $jwtCertificateGenerator;

    private string $privatePath;

    private string $publicPath;

    private string $dirname;

    protected function setUp(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $this->jwtCertificateGenerator = new JwtCertificateGenerator();

        $this->privatePath = __DIR__ . '/private.pem';
        $this->publicPath = __DIR__ . '/public.pem';

        $this->dirname = 'does-not-exist';
    }

    protected function tearDown(): void
    {
        unlink($this->privatePath);
        unlink($this->publicPath);

        if (is_dir($this->dirname)) {
            rmdir($this->dirname);
        }
    }

    public function testGenerate(): void
    {
        $passphrase = 'test';

        $this->jwtCertificateGenerator->generate(
            $this->privatePath,
            $this->publicPath,
            $passphrase
        );

        static::assertFileExists($this->privatePath);
        static::assertFileExists($this->publicPath);

        static::assertFileIsReadable($this->privatePath);
        static::assertFileIsReadable($this->publicPath);

        $data = 'test data';

        $privateCertificate = file_get_contents($this->privatePath);
        static::assertIsString($privateCertificate);
        $privateKey = openssl_pkey_get_private($privateCertificate, $passphrase);
        static::assertInstanceOf(\OpenSSLAsymmetricKey::class, $privateKey);

        openssl_sign($data, $signature, $privateKey);

        $publicCertificate = file_get_contents($this->publicPath);
        static::assertIsString($publicCertificate);

        static::assertSame(
            1,
            openssl_verify($data, $signature, $publicCertificate)
        );
    }

    public function testGenerateWithoutPassphrase(): void
    {
        $this->jwtCertificateGenerator->generate(
            $this->privatePath,
            $this->publicPath,
        );

        static::assertFileExists($this->privatePath);
        static::assertFileExists($this->publicPath);

        static::assertFileIsReadable($this->privatePath);
        static::assertFileIsReadable($this->publicPath);

        $data = 'test data';
        $privateCertificate = file_get_contents($this->privatePath);
        static::assertIsString($privateCertificate);
        $privateKey = openssl_pkey_get_private($privateCertificate);
        static::assertInstanceOf(\OpenSSLAsymmetricKey::class, $privateKey);

        openssl_sign($data, $signature, $privateKey);

        $publicCertificate = file_get_contents($this->publicPath);
        static::assertIsString($publicCertificate);

        static::assertSame(
            1,
            openssl_verify($data, $signature, $publicCertificate)
        );
    }

    public function testGenerateInNonExistingDirectory(): void
    {
        // Update variables to point to a non-existing directory
        $this->privatePath = __DIR__ . $this->dirname . '/private.pem';
        $this->publicPath = __DIR__ . $this->dirname . '/public.pem';

        static::assertDirectoryDoesNotExist($this->dirname);

        // We can just call the other test methods, so we don't need to repeat their code.
        $this->testGenerate();
        $this->testGenerateWithoutPassphrase();
    }
}
