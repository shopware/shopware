<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Command\CheckShopIdCommand;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\FingerprintGenerator;
use Shopware\Core\Framework\App\ShopId\FingerprintMatch;
use Shopware\Core\Framework\App\ShopId\FingerprintMismatch;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CheckShopIdCommand::class)]
class CheckShopIdCommandTest extends TestCase
{
    public function testDisplaysHintThatNoShopIsExistsYet(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(null, null);

        $fingerprintGenerator = $this->createMock(FingerprintGenerator::class);

        $commandTester = new CommandTester(
            new CheckShopIdCommand($systemConfigService, $fingerprintGenerator)
        );

        $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('No shop ID has been generated yet.', $commandTester->getDisplay());
    }

    public function testDisplaysShopId(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(null, ['value' => 'shop-id-v1', 'app_url' => 'https://foo.bar']);

        $fingerprintGenerator = $this->createMock(FingerprintGenerator::class);
        $fingerprintGenerator->expects($this->once())
            ->method('matchFingerprints')
            ->willReturn(new FingerprintComparisonResult([], [], 75));

        $commandTester = new CommandTester(
            new CheckShopIdCommand($systemConfigService, $fingerprintGenerator)
        );

        $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('Shop ID: shop-id-v1', $commandTester->getDisplay());
        static::assertStringContainsString('Version: 1', $commandTester->getDisplay());
    }

    public function testDisplaysFingerprintsAndSuggestionIfFingerprintsDoNotMatch(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->exactly(1))
            ->method('get')
            ->willReturn([
                'id' => 'shop-id-v2',
                'version' => 2,
                'fingerprints' => [
                    'fingerprint-1' => 'stored-stamp-1',
                    'fingerprint-2' => 'stored-stamp-2',
                    'fingerprint-3' => 'stored-stamp-3',
                ],
            ]);

        $fingerprintGenerator = $this->createMock(FingerprintGenerator::class);
        $fingerprintGenerator->expects($this->once())
            ->method('matchFingerprints')
            ->willReturn(new FingerprintComparisonResult([
                'fingerprint1' => new FingerprintMatch('fingerprint-1', 'stored-stamp-1', 25),
            ], [
                'fingerprint2' => new FingerprintMismatch('fingerprint-2', 'stored-stamp-2', 'expected-stamp-2', 50),
                'fingerprint3' => new FingerprintMismatch('fingerprint-3', 'stored-stamp-3', 'expected-stamp-3', 75),
            ], 75));

        $commandTester = new CommandTester(
            new CheckShopIdCommand($systemConfigService, $fingerprintGenerator)
        );

        $commandTester->execute([]);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        static::assertStringContainsString('Shop ID change suggested (Score: 125/75).', $commandTester->getDisplay());
    }

    public function testDisplaysFingerprintsAndSuggestionIfFingerprintsMatch(): void
    {
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->expects($this->exactly(1))
            ->method('get')
            ->willReturn([
                'id' => 'shop-id-v2',
                'version' => 2,
                'fingerprints' => [
                    'fingerprint-1' => 'stored-stamp-1',
                    'fingerprint-2' => 'stored-stamp-2',
                    'fingerprint-3' => 'stored-stamp-3',
                ],
            ]);

        $fingerprintGenerator = $this->createMock(FingerprintGenerator::class);
        $fingerprintGenerator->expects($this->once())
            ->method('matchFingerprints')
            ->willReturn(new FingerprintComparisonResult([
                'fingerprint1' => new FingerprintMatch('fingerprint-1', 'stored-stamp-1', 25),
                'fingerprint2' => new FingerprintMatch('fingerprint-2', 'stored-stamp-2', 50),
                'fingerprint3' => new FingerprintMatch('fingerprint-3', 'stored-stamp-3', 75),
            ], [], 75));

        $commandTester = new CommandTester(
            new CheckShopIdCommand($systemConfigService, $fingerprintGenerator)
        );

        $commandTester->execute([]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertStringContainsString('Shop ID change not suggested.', $commandTester->getDisplay());
    }
}
