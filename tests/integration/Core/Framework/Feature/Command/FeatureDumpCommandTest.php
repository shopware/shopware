<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Feature\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\Command\FeatureDumpCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
class FeatureDumpCommandTest extends TestCase
{
    use KernelTestBehaviour;

    private string $dumpPath;

    private ?string $originalContent = null;

    protected function setUp(): void
    {
        $this->dumpPath = static::getKernel()->getProjectDir() . '/var/config_js_features.json';
        $this->originalContent = \is_file($this->dumpPath) ? (string) \file_get_contents($this->dumpPath) : null;
    }

    protected function tearDown(): void
    {
        if ($this->originalContent === null) {
            @\unlink($this->dumpPath);

            return;
        }

        \file_put_contents($this->dumpPath, $this->originalContent);
    }

    public function testDumpsAllFeatureFlagsToJsonFile(): void
    {
        $commandTester = new CommandTester(static::getContainer()->get(FeatureDumpCommand::class));

        static::assertSame(Command::SUCCESS, $commandTester->execute([]));
        static::assertStringContainsString('Successfully dumped js feature configuration', $commandTester->getDisplay());

        static::assertFileExists($this->dumpPath);
        static::assertSame(
            Feature::getAll(),
            \json_decode((string) \file_get_contents($this->dumpPath), true, 512, \JSON_THROW_ON_ERROR)
        );
    }
}
