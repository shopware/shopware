<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Feature\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\Command\FeatureDumpCommand;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(FeatureDumpCommand::class)]
class FeatureDumpCommandTest extends TestCase
{
    #[TestDox('All feature flags are dumped as JSON into var/config_js_features.json of the project')]
    public function testDumpsAllFeatureFlagsToJsonFile(): void
    {
        $kernel = static::createStub(Kernel::class);
        $kernel->method('getProjectDir')->willReturn('/project');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem
            ->expects($this->once())
            ->method('dumpFile')
            ->with(
                '/project/var/config_js_features.json',
                json_encode(Feature::getAll(), \JSON_THROW_ON_ERROR)
            );

        $commandTester = new CommandTester(new FeatureDumpCommand($kernel, $filesystem));

        static::assertSame(Command::SUCCESS, $commandTester->execute([]));
        static::assertStringContainsString('Successfully dumped js feature configuration', $commandTester->getDisplay());
    }
}
