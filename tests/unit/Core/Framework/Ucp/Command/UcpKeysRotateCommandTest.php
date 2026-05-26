<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Ucp\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Ucp\Command\UcpKeysRotateCommand;
use Shopware\Core\Framework\Ucp\DataAbstractionLayer\Entity\UcpSigningKeyEntity;
use Shopware\Core\Framework\Ucp\Jwt\UcpSigningKeyProvider;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(UcpKeysRotateCommand::class)]
class UcpKeysRotateCommandTest extends TestCase
{
    public function testRotateRequiresSalesChannel(): void
    {
        $provider = $this->createMock(UcpSigningKeyProvider::class);
        $provider->expects(static::never())->method('create');

        $tester = new CommandTester(new UcpKeysRotateCommand($provider));
        $exit = $tester->execute([]);

        static::assertSame(Command::INVALID, $exit);
        static::assertStringContainsString('--sales-channel is required', $tester->getDisplay());
    }

    public function testRotateCreatesNewActiveKeyAndReportsPreviousKid(): void
    {
        $salesChannelId = '0190f7bfb9c97e8c8d28b6e5d6a45f00';
        $previous = $this->makeKey('ucp_2026_oldkid', UcpSigningKeyEntity::STATUS_RETIRING);
        $created = $this->makeKey('ucp_2026_newkid', UcpSigningKeyEntity::STATUS_ACTIVE);

        $provider = $this->createMock(UcpSigningKeyProvider::class);
        $provider->expects(static::once())
            ->method('getActive')
            ->with($salesChannelId, static::isInstanceOf(Context::class))
            ->willReturn($previous);
        $provider->expects(static::once())
            ->method('create')
            ->with($salesChannelId, UcpSigningKeyEntity::ALGORITHM_ES256, static::isInstanceOf(Context::class), true)
            ->willReturn($created);

        $tester = new CommandTester(new UcpKeysRotateCommand($provider));
        $exit = $tester->execute(['--sales-channel' => $salesChannelId]);

        static::assertSame(Command::SUCCESS, $exit);
        $output = $tester->getDisplay();
        static::assertStringContainsString('ucp_2026_newkid', $output);
        static::assertStringContainsString('ucp_2026_oldkid', $output);
        static::assertStringContainsString('now retiring', $output);
    }

    public function testRotateWithoutPreviousActiveKey(): void
    {
        $salesChannelId = '0190f7bfb9c97e8c8d28b6e5d6a45f00';
        $created = $this->makeKey('ucp_2026_newkid', UcpSigningKeyEntity::STATUS_ACTIVE);

        $provider = $this->createMock(UcpSigningKeyProvider::class);
        $provider->method('getActive')->willReturn(null);
        $provider->expects(static::once())
            ->method('create')
            ->with($salesChannelId, UcpSigningKeyEntity::ALGORITHM_ES384, static::isInstanceOf(Context::class), true)
            ->willReturn($created);

        $tester = new CommandTester(new UcpKeysRotateCommand($provider));
        $exit = $tester->execute([
            '--sales-channel' => $salesChannelId,
            '--algorithm' => UcpSigningKeyEntity::ALGORITHM_ES384,
        ]);

        static::assertSame(Command::SUCCESS, $exit);
        static::assertStringContainsString('no previous active key', $tester->getDisplay());
    }

    private function makeKey(string $kid, string $status): UcpSigningKeyEntity
    {
        $key = new UcpSigningKeyEntity();
        $key->setId(str_pad('a' . $kid, 32, '0', \STR_PAD_RIGHT));
        $key->setSalesChannelId('0190f7bfb9c97e8c8d28b6e5d6a45f00');
        $key->setKid($kid);
        $key->setAlgorithm(UcpSigningKeyEntity::ALGORITHM_ES256);
        $key->setPublicJwk(['kty' => 'EC', 'crv' => 'P-256', 'kid' => $kid]);
        $key->setPrivateKeyPemEncrypted('enc');
        $key->setStatus($status);

        return $key;
    }
}
