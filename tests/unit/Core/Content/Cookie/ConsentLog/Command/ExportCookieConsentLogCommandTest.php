<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\ConsentLog\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\ConsentLog\AbstractCookieConsentLogStorage;
use Shopware\Core\Content\Cookie\ConsentLog\Command\ExportCookieConsentLogCommand;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentAction;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentDecision;
use Shopware\Core\Content\Cookie\ConsentLog\CookieConsentRecord;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ExportCookieConsentLogCommand::class)]
class ExportCookieConsentLogCommandTest extends TestCase
{
    public function testItExportsTheRequestedRangeAsOneJsonArray(): void
    {
        $storage = $this->createMock(AbstractCookieConsentLogStorage::class);
        $storage->expects($this->once())
            ->method('iterate')
            ->with(
                static::callback(static fn (\DateTimeImmutable $from) => $from->format('Y-m-d H:i:s') === '2026-01-01 00:00:00'),
                static::callback(static fn (\DateTimeImmutable $to) => $to->format('Y-m-d H:i:s') === '2026-07-01 00:00:00'),
                TestDefaults::SALES_CHANNEL,
            )
            ->willReturn($this->records());

        $tester = new CommandTester(new ExportCookieConsentLogCommand($storage));
        $exitCode = $tester->execute([
            '--from' => '2026-01-01',
            '--to' => '2026-07-01',
            '--sales-channel' => TestDefaults::SALES_CHANNEL,
        ]);

        static::assertSame(Command::SUCCESS, $exitCode);

        $output = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertIsArray($output);
        static::assertCount(2, $output);
        static::assertSame('visitor-a', $output[0]['consentId']);
        static::assertSame(['cookie.groupStatistical' => 'accepted'], $output[0]['groupDecisions']);
        static::assertSame('visitor-b', $output[1]['consentId']);
    }

    public function testAnEmptyRangeIsAnEmptyJsonArray(): void
    {
        $storage = static::createStub(AbstractCookieConsentLogStorage::class);
        $storage->method('iterate')->willReturn([]);

        $tester = new CommandTester(new ExportCookieConsentLogCommand($storage));
        $tester->execute([]);

        static::assertSame("[]\n", $tester->getDisplay());
    }

    public function testItExportsCsvWithAHeaderRow(): void
    {
        $storage = static::createStub(AbstractCookieConsentLogStorage::class);
        $storage->method('iterate')->willReturn($this->records());

        $tester = new CommandTester(new ExportCookieConsentLogCommand($storage));
        $exitCode = $tester->execute(['--format' => 'csv']);

        static::assertSame(Command::SUCCESS, $exitCode);

        $lines = explode("\n", trim($tester->getDisplay()));
        static::assertCount(3, $lines);
        static::assertSame(
            'consentId,createdAt,consentAction,salesChannelId,languageId,configHash,groupDecisions,acceptedCookies',
            $lines[0],
        );
        static::assertSame(
            'visitor-a,2026-07-13T12:00:00.000+00:00,accept_all,sales-channel-id,language-id,hash,"{""cookie.groupStatistical"":""accepted""}","[""lorem"",""ipsum""]"',
            $lines[1],
        );
        static::assertStringStartsWith('visitor-b,', $lines[2]);
    }

    public function testItRejectsAnUnknownFormat(): void
    {
        $storage = $this->createMock(AbstractCookieConsentLogStorage::class);
        $storage->expects($this->never())->method('iterate');

        $tester = new CommandTester(new ExportCookieConsentLogCommand($storage));
        $exitCode = $tester->execute(['--format' => 'xml']);

        static::assertSame(Command::INVALID, $exitCode);
        static::assertStringContainsString('Unknown format "xml"', $tester->getDisplay());
    }

    public function testItRejectsAnInvalidSalesChannelId(): void
    {
        $storage = $this->createMock(AbstractCookieConsentLogStorage::class);
        $storage->expects($this->never())->method('iterate');

        $tester = new CommandTester(new ExportCookieConsentLogCommand($storage));
        $exitCode = $tester->execute(['--sales-channel' => 'not-a-uuid']);

        static::assertSame(Command::INVALID, $exitCode);
        static::assertStringContainsString('"not-a-uuid" is not a valid sales channel id', $tester->getDisplay());
    }

    public function testItRejectsAnUnparsableDate(): void
    {
        $storage = $this->createMock(AbstractCookieConsentLogStorage::class);
        $storage->expects($this->never())->method('iterate');

        $tester = new CommandTester(new ExportCookieConsentLogCommand($storage));
        $exitCode = $tester->execute(['--from' => 'yesterday-ish']);

        static::assertSame(Command::INVALID, $exitCode);
    }

    /**
     * @return list<CookieConsentRecord>
     */
    private function records(): array
    {
        $createdAt = new \DateTimeImmutable('2026-07-13 12:00:00', new \DateTimeZone('UTC'));

        return [
            new CookieConsentRecord(
                consentId: 'visitor-a',
                consentAction: CookieConsentAction::ACCEPT_ALL,
                groupDecisions: ['cookie.groupStatistical' => CookieConsentDecision::ACCEPTED],
                acceptedCookies: ['lorem', 'ipsum'],
                configHash: 'hash',
                salesChannelId: 'sales-channel-id',
                languageId: 'language-id',
                createdAt: $createdAt,
            ),
            new CookieConsentRecord(
                consentId: 'visitor-b',
                consentAction: CookieConsentAction::ACCEPT_REQUIRED,
                groupDecisions: ['cookie.groupStatistical' => CookieConsentDecision::REJECTED],
                acceptedCookies: [],
                configHash: 'hash',
                salesChannelId: 'sales-channel-id',
                languageId: 'language-id',
                createdAt: $createdAt->modify('+1 hour'),
            ),
        ];
    }
}
