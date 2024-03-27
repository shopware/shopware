<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Maintenance\System\Command\SystemGenerateJwtSecretCommand;
use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 *
 * @deprecated tag:v6.7.0 - reason:remove-command - will be removed without a replacement
 */
#[CoversClass(SystemGenerateJwtSecretCommand::class)]
class SystemGenerateJwtSecretCommandTest extends TestCase
{
    public function testMissingPassphrase(): void
    {
        $tester = new CommandTester(new SystemGenerateJwtSecretCommand(
            __DIR__,
            $this->createMock(JwtCertificateGenerator::class),
            false
        ));

        $tester->execute(['--jwt-passphrase' => false]);
        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('Passphrase is invalid', $tester->getDisplay());
    }

    public function testUseEnv(): void
    {
        $tester = new CommandTester(new SystemGenerateJwtSecretCommand(
            __DIR__,
            $this->getGenerator(),
            false
        ));

        $tester->execute(['--use-env' => true]);
        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertStringContainsString('JWT_PUBLIC_KEY=Mg==', $tester->getDisplay());
    }

    public function testPrivateWriteInvalidPath(): void
    {
        $tester = new CommandTester(new SystemGenerateJwtSecretCommand(
            __DIR__,
            $this->getGenerator(),
            false
        ));

        $tester->execute(['--private-key-path' => false]);
        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('Private key path is invalid', $tester->getDisplay());
    }

    public function testPublicWriteInvalidPath(): void
    {
        $tester = new CommandTester(new SystemGenerateJwtSecretCommand(
            __DIR__,
            $this->getGenerator(),
            false
        ));

        $tester->execute(['--public-key-path' => false]);
        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('Public key path is invalid', $tester->getDisplay());
    }

    public function testPrivateKeyExists(): void
    {
        $tester = new CommandTester(new SystemGenerateJwtSecretCommand(
            __DIR__,
            $this->getGenerator(),
            false
        ));

        $tester->execute(['--private-key-path' => __DIR__]);
        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('Cannot create private key', $tester->getDisplay());
    }

    public function testPublicKeyExists(): void
    {
        $tester = new CommandTester(new SystemGenerateJwtSecretCommand(
            __DIR__,
            $this->getGenerator(),
            false
        ));

        $tester->execute(['--public-key-path' => __DIR__]);
        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('Cannot create public key', $tester->getDisplay());
    }

    public function testGenerationForce(): void
    {
        $tmpDir = sys_get_temp_dir() . '/' . uniqid('jwt-test', true);
        $fs = new Filesystem();
        $fs->mkdir($tmpDir);
        $fs->dumpFile($tmpDir . '/private.pem', 'test');
        $fs->dumpFile($tmpDir . '/public.pem', 'test');

        $generator = $this->createMock(JwtCertificateGenerator::class);
        $generator
            ->expects(static::once())
            ->method('generate');

        $tester = new CommandTester(new SystemGenerateJwtSecretCommand(
            $tmpDir,
            $generator,
            false
        ));

        $tester->execute([
            '--force' => true,
            '--private-key-path' => $tmpDir . '/private.pem',
            '--public-key-path' => $tmpDir . '/public.pem',
        ]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());

        $fs->remove($tmpDir);
    }

    private function getGenerator(): JwtCertificateGenerator&MockObject
    {
        $jwtCertificateGenerator = $this->createMock(JwtCertificateGenerator::class);
        $jwtCertificateGenerator
            ->method('generateString')
            ->willReturn(['1', '2']);

        return $jwtCertificateGenerator;
    }
}
