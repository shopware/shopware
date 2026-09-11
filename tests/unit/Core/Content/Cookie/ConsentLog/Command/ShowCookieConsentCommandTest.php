<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\ConsentLog\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ConsentLog\AbstractCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\ConsentLog\Command\ShowCookieConsentCommand;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentConfigSnapshot;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentDecision;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentSource;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShowCookieConsentCommand::class)]
class ShowCookieConsentCommandTest extends TestCase
{
    public function testItPrintsTheDecisionsAndTheirBannerConfigurations(): void
    {
        $createdAt = new \DateTimeImmutable('2026-07-13 12:00:00', new \DateTimeZone('UTC'));
        $records = [
            $this->record('hash-1', CookieConsentAction::ACCEPT_ALL, $createdAt),
            $this->record('hash-1', CookieConsentAction::ACCEPT_REQUIRED, $createdAt->modify('+1 day')),
            $this->record('hash-2', CookieConsentAction::ACCEPT_ALL, $createdAt->modify('+2 days')),
        ];

        $storage = $this->createMock(AbstractCookieConsentLogStorage::class);
        $storage->expects($this->once())->method('findByConsentId')->with('consent-id')->willReturn($records);
        // Each configuration is looked up once, no matter how many decisions reference it
        $storage->expects($this->exactly(2))
            ->method('findSnapshot')
            ->willReturnCallback(static fn (string $hash) => $hash === 'hash-1'
                ? new CookieConsentConfigSnapshot('hash-1', [['technicalName' => 'cookie.groupRequired']], $createdAt)
                : null);

        $tester = new CommandTester(new ShowCookieConsentCommand($storage));
        $exitCode = $tester->execute(['consent-id' => 'consent-id']);

        static::assertSame(Command::SUCCESS, $exitCode);

        $output = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($output);
        static::assertSame('consent-id', $output['consentId']);
        static::assertSame(['accept_all', 'accept_required', 'accept_all'], array_column($output['decisions'], 'consentAction'));
        static::assertSame(['hash-1', 'hash-2'], array_keys($output['configurations']));
        static::assertSame([['technicalName' => 'cookie.groupRequired']], $output['configurations']['hash-1']['cookieGroups']);
        // A missing snapshot is reported as such instead of failing the whole request
        static::assertNull($output['configurations']['hash-2']);
    }

    public function testItFailsWhenNothingIsRecordedForTheConsentId(): void
    {
        $storage = $this->createMock(AbstractCookieConsentLogStorage::class);
        $storage->method('findByConsentId')->willReturn([]);
        $storage->expects($this->never())->method('findSnapshot');

        $tester = new CommandTester(new ShowCookieConsentCommand($storage));
        $exitCode = $tester->execute(['consent-id' => 'unknown'], ['capture_stderr_separately' => true]);

        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('No consent decisions are recorded for "unknown"', $tester->getErrorOutput());

        $output = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(['consentId' => 'unknown', 'decisions' => [], 'configurations' => []], $output);
    }

    private function record(string $configHash, CookieConsentAction $action, \DateTimeImmutable $createdAt): CookieConsentRecord
    {
        return new CookieConsentRecord(
            consentId: 'consent-id',
            consentAction: $action,
            source: CookieConsentSource::BANNER,
            groupDecisions: ['cookie.groupRequired' => CookieConsentDecision::ACCEPTED],
            acceptedCookies: [],
            configHash: $configHash,
            salesChannelId: 'sales-channel-id',
            languageId: 'language-id',
            createdAt: $createdAt,
        );
    }
}
