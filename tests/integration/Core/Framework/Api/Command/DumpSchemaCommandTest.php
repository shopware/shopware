<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\Command\DumpSchemaCommand;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('framework')]
class DumpSchemaCommandTest extends TestCase
{
    private Filesystem $filesystem;

    private string $directory;

    protected function setUp(): void
    {
        $this->filesystem = new Filesystem();
        $this->directory = sys_get_temp_dir() . '/' . uniqid('dump-schema-command-test-', true);
        $this->filesystem->mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->directory);
    }

    public function testWritesTheSchemaToTheOutfile(): void
    {
        $definitionService = $this->createMock(DefinitionService::class);
        $definitionService->expects($this->once())->method('getSchema')->willReturn(['product' => ['entity' => 'product']]);
        $outfile = $this->directory . '/schema.json';

        $commandTester = new CommandTester(new DumpSchemaCommand($definitionService, static::createStub(CacheInterface::class)));
        $commandTester->execute(['outfile' => $outfile], ['capture_stderr_separately' => true]);

        $commandTester->assertCommandIsSuccessful();
        static::assertStringEqualsFile($outfile, '{"product":{"entity":"product"}}');
    }
}
