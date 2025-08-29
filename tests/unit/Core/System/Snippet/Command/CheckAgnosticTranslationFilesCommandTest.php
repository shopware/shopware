<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\CheckAgnosticTranslationFilesCommand;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CheckAgnosticTranslationFilesCommand::class)]
class CheckAgnosticTranslationFilesCommandTest extends TestCase
{
    private readonly Filesystem $filesystem;

    protected function setUp(): void
    {
        $this->filesystem = $this->createMock(Filesystem::class);
    }

    public function testNothingToDo(): void
    {
        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $tester->execute([]);
        $x = $tester->getDisplay();
        dump($x);
        $tester->assertCommandIsSuccessful();
    }

    private function getCommand(): CheckAgnosticTranslationFilesCommand
    {
        return new CheckAgnosticTranslationFilesCommand($this->filesystem);
    }
}
