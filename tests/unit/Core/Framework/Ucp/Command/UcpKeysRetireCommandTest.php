<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Ucp\Command\UcpKeysRetireCommand;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSigningKeyEntity;
use Shopware\Core\Framework\Ucp\Jwt\UcpSigningKeyProvider;
use Shopware\Core\Framework\Ucp\UcpException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(UcpKeysRetireCommand::class)]
class UcpKeysRetireCommandTest extends TestCase
{
    public function testRetireRequiresSalesChannel(): void
    {
        $provider = $this->createMock(UcpSigningKeyProvider::class);
        $provider->expects(static::never())->method('retire');

        $tester = new CommandTester(new UcpKeysRetireCommand($provider));
        $exit = $tester->execute(['--kid' => 'ucp_2026_kid']);

        static::assertSame(Command::INVALID, $exit);
        static::assertStringContainsString('--sales-channel is required', $tester->getDisplay());
    }

    public function testRetireRequiresKid(): void
    {
        $provider = $this->createMock(UcpSigningKeyProvider::class);
        $provider->expects(static::never())->method('retire');

        $tester = new CommandTester(new UcpKeysRetireCommand($provider));
        $exit = $tester->execute(['--sales-channel' => '0190f7bfb9c97e8c8d28b6e5d6a45f00']);

        static::assertSame(Command::INVALID, $exit);
        static::assertStringContainsString('--kid is required', $tester->getDisplay());
    }

    public function testRetireDelegatesAndReportsState(): void
    {
        $salesChannelId = '0190f7bfb9c97e8c8d28b6e5d6a45f00';
        $kid = 'ucp_2026_kid';
        $now = new \DateTimeImmutable('2026-05-26T08:00:00+00:00');

        $key = new UcpSigningKeyEntity();
        $key->setId(str_pad('a', 32, '0', \STR_PAD_RIGHT));
        $key->setSalesChannelId($salesChannelId);
        $key->setKid($kid);
        $key->setAlgorithm(UcpSigningKeyEntity::ALGORITHM_ES256);
        $key->setPublicJwk(['kty' => 'EC', 'crv' => 'P-256', 'kid' => $kid]);
        $key->setPrivateKeyPemEncrypted('enc');
        $key->setStatus(UcpSigningKeyEntity::STATUS_RETIRING);
        $key->setRetiringAt($now);

        $provider = $this->createMock(UcpSigningKeyProvider::class);
        $provider->expects(static::once())
            ->method('retire')
            ->with($salesChannelId, $kid, static::isInstanceOf(Context::class))
            ->willReturn($key);

        $tester = new CommandTester(new UcpKeysRetireCommand($provider));
        $exit = $tester->execute(['--sales-channel' => $salesChannelId, '--kid' => $kid]);

        static::assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        static::assertStringContainsString($kid, $display);
        static::assertStringContainsString(UcpSigningKeyEntity::STATUS_RETIRING, $display);
        static::assertStringContainsString('2026-05-26T08:00:00+00:00', $display);
    }

    public function testRetirePropagatesProviderException(): void
    {
        $provider = $this->createMock(UcpSigningKeyProvider::class);
        $provider->method('retire')
            ->willThrowException(UcpException::keyNotFound('missing', '0190f7bfb9c97e8c8d28b6e5d6a45f00'));

        $tester = new CommandTester(new UcpKeysRetireCommand($provider));

        $this->expectException(UcpException::class);
        $tester->execute([
            '--sales-channel' => '0190f7bfb9c97e8c8d28b6e5d6a45f00',
            '--kid' => 'missing',
        ]);
    }
}
