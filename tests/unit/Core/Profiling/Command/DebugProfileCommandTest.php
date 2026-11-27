<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Profiling\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Command\DebugProfileCommand;
use Shopware\Core\Profiling\Export\ExportLimits;
use Shopware\Core\Profiling\Export\ProfileExporter;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DebugProfileCommand::class)]
class DebugProfileCommandTest extends TestCase
{
    public function testListsProfilesWithoutToken(): void
    {
        $exporter = $this->createMock(ProfileExporter::class);
        $exporter->expects($this->once())
            ->method('list')
            ->with(null, null, null, 'request', 20, true)
            ->willReturn([['token' => 'aaa111']]);

        $tester = $this->execute($exporter, []);

        $tester->assertCommandIsSuccessful();
        static::assertSame(['profiles' => [['token' => 'aaa111']]], json_decode($tester->getDisplay(), true));
    }

    public function testPassesListFilters(): void
    {
        $exporter = $this->createMock(ProfileExporter::class);
        $exporter->expects($this->once())
            ->method('list')
            ->with('/detail', 'GET', '200', 'command', 5, false)
            ->willReturn([]);

        $tester = $this->execute($exporter, ['--url' => '/detail', '--method' => 'GET', '--status' => '200', '--type' => 'command', '--limit' => '5', '--keep-secrets' => true]);

        $tester->assertCommandIsSuccessful();
    }

    public function testExportsTheSelectedPanelsOfTheLatestProfile(): void
    {
        $profile = new Profile('aaa111');
        $exporter = $this->createMock(ProfileExporter::class);
        $exporter->expects($this->once())
            ->method('load')
            ->with('latest', '/detail', null, null, 'request')
            ->willReturn($profile);
        $exporter->expects($this->once())
            ->method('panels')
            ->willReturn(['app.connection_collector' => 'db', 'time' => 'time']);
        $exporter->expects($this->once())
            ->method('export')
            ->with($profile, ['db', 'time'], false, ExportLimits::summary(), true)
            ->willReturn(['token' => 'aaa111', 'url' => 'https://shop.test/ä']);

        $tester = $this->execute($exporter, ['token' => 'latest', '--url' => '/detail', '--panel' => 'db, time']);

        $tester->assertCommandIsSuccessful();
        static::assertSame("{\n    \"token\": \"aaa111\",\n    \"url\": \"https://shop.test/ä\"\n}\n", $tester->getDisplay());
    }

    public function testExportsRawDataWithCustomLimitsAndSecrets(): void
    {
        $profile = new Profile('aaa111');
        $exporter = $this->createMock(ProfileExporter::class);
        $exporter->expects($this->once())->method('load')->willReturn($profile);
        $exporter->expects($this->once())->method('panels')->willReturn(['time' => 'time']);
        $exporter->expects($this->once())
            ->method('export')
            ->with($profile, [], true, new ExportLimits(depth: 12, items: 50, string: 0), false)
            ->willReturn([]);

        $tester = $this->execute($exporter, ['token' => 'aaa111', '--full' => true, '--max-items' => '50', '--max-string' => '0', '--keep-secrets' => true]);

        $tester->assertCommandIsSuccessful();
    }

    public function testFailsForUnknownPanels(): void
    {
        $exporter = $this->createMock(ProfileExporter::class);
        $exporter->expects($this->once())->method('load')->willReturn(new Profile('aaa111'));
        $exporter->expects($this->once())->method('panels')->willReturn(['app.connection_collector' => 'db', 'time' => 'time']);
        $exporter->expects($this->never())->method('export');

        $tester = $this->execute($exporter, ['token' => 'aaa111', '--panel' => 'sql,app.connection_collector']);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertSame('', $tester->getDisplay());
        static::assertStringContainsString('Unknown panel "sql". Available panels: db, time', $tester->getErrorOutput());
    }

    public function testFailsWhenNoProfileMatches(): void
    {
        $exporter = static::createStub(ProfileExporter::class);
        $exporter->method('load')->willReturn(null);

        $tester = $this->execute($exporter, ['token' => 'latest']);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('No profile found for "latest".', $tester->getErrorOutput());
    }

    public function testWritesTheJsonToAFile(): void
    {
        $exporter = static::createStub(ProfileExporter::class);
        $exporter->method('list')->willReturn([]);
        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects($this->once())
            ->method('dumpFile')
            ->with('/tmp/profiles.json', "{\n    \"profiles\": []\n}" . \PHP_EOL);

        $tester = $this->execute($exporter, ['--output' => '/tmp/profiles.json'], $filesystem);

        $tester->assertCommandIsSuccessful();
        static::assertSame('/tmp/profiles.json (23 bytes)' . \PHP_EOL, $tester->getDisplay());
    }

    public function testRejectsAnUnknownType(): void
    {
        $this->expectExceptionObject(new \InvalidArgumentException('The option "--type" must be one of "request", "command".'));

        $this->execute(static::createStub(ProfileExporter::class), ['--type' => 'worker']);
    }

    public function testRejectsANegativeLimit(): void
    {
        $exporter = static::createStub(ProfileExporter::class);
        $exporter->method('load')->willReturn(new Profile('aaa111'));
        $exporter->method('panels')->willReturn([]);

        $this->expectExceptionObject(new \InvalidArgumentException('The option "--max-depth" must be a number of 0 or more.'));

        $this->execute($exporter, ['token' => 'aaa111', '--max-depth' => '-1']);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function execute(ProfileExporter $exporter, array $input, ?Filesystem $filesystem = null): CommandTester
    {
        $tester = new CommandTester(new DebugProfileCommand($exporter, $filesystem ?? static::createStub(Filesystem::class)));
        $tester->execute($input, ['capture_stderr_separately' => true]);

        return $tester;
    }
}
