<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Maintenance\System\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;

/**
 * @covers \Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator
 *
 * @internal
 */
class JwtCertificateGeneratorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private JwtCertificateGenerator $jwtCertificateGenerator;

    private string $privatePath;

    private string $publicPath;

    private string $dirname;

    public function setUp(): void
    {
        $this->jwtCertificateGenerator = $this->getContainer()->get(JwtCertificateGenerator::class);

        $this->privatePath = __DIR__ . '/private.pem';
        $this->publicPath = __DIR__ . '/public.pem';

        $this->dirname = 'does-not-exist';
    }

    public function tearDown(): void
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

        /** @var string $privateCertificate */
        $privateCertificate = file_get_contents($this->privatePath);
        /** @var \OpenSSLAsymmetricKey $privateKey */
        $privateKey = openssl_pkey_get_private($privateCertificate, $passphrase);

        openssl_sign($data, $signature, $privateKey);

        /** @var string $publicCertificate */
        $publicCertificate = file_get_contents($this->publicPath);

        static::assertEquals(
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
        /** @var string $privateCertificate */
        $privateCertificate = file_get_contents($this->privatePath);
        /** @var \OpenSSLAsymmetricKey $privateKey */
        $privateKey = openssl_pkey_get_private($privateCertificate);

        openssl_sign($data, $signature, $privateKey);

        /** @var string $publicCertificate */
        $publicCertificate = file_get_contents($this->publicPath);

        static::assertEquals(
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
