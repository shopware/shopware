<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStorage;
use Shopware\Core\Framework\App\Command\AppListCommand;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppListCommand::class)]
class AppListCommandTest extends TestCase
{
    private MockObject&AppStorage $appStorage;

    private AppListCommand $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appStorage = $this->createMock(AppStorage::class);

        $this->command = new AppListCommand($this->appStorage);
    }

    public function testCommand(): void
    {
        $app1 = new AppEntity();
        $app2 = new AppEntity();

        $app1->setUniqueIdentifier('1');
        $app1->assign([
            'active' => true,
            'name' => 'App List Test',
            'label' => 'alt',
            'version' => '1.0.0',
            'author' => 'Shopware AG',
        ]);

        $app2->setUniqueIdentifier('2');
        $app2->assign([
            'active' => false,
            'name' => 'Inactive App',
            'label' => 'Inactive App with a very long label that will be truncated',
            'version' => '2.0.0',
            'author' => 'Test Developer',
        ]);

        $this->appStorage
            ->expects($this->once())
            ->method('findAll')
            ->with(static::isInstanceOf(Context::class))
            ->willReturn(new AppCollection([$app2, $app1]));

        $commandTester = $this->executeCommand([]);
        static::assertSame(0, $commandTester->getStatusCode());

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('Shopware App Service', $display);
        static::assertStringContainsString('App List Test', $display);
        static::assertStringContainsString('Inactive App', $display);
        $appListTestPosition = mb_strpos($display, 'App List Test');
        $inactiveAppPosition = mb_strpos($display, 'Inactive App');
        static::assertIsInt($appListTestPosition);
        static::assertIsInt($inactiveAppPosition);
        static::assertLessThan($inactiveAppPosition, $appListTestPosition);
        static::assertStringContainsString('2 apps, 1 active', $display);
    }

    public function testFilter(): void
    {
        $filterValue = 'test-app';

        $this->appStorage
            ->expects($this->once())
            ->method('findAllWithNameOrLabel')
            ->with($filterValue, static::isInstanceOf(Context::class))
            ->willReturn(new AppCollection());

        $commandTester = $this->executeCommand(['--filter' => $filterValue]);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertStringContainsString('Filtering for: ' . $filterValue, trim($commandTester->getDisplay()));
    }

    public function testFormatJsonOutput(): void
    {
        $entities = [
            $app1 = new AppEntity(),
            $app2 = new AppEntity(),
        ];

        $app1->setUniqueIdentifier('1');
        $app1->setName('Beta App');
        $app2->setUniqueIdentifier('2');
        $app2->setName('Alpha App');

        $this->appStorage
            ->expects($this->once())
            ->method('findAll')
            ->with(static::isInstanceOf(Context::class))
            ->willReturn(new AppCollection($entities));

        $options = ['--format' => 'json'];
        $json = json_encode([$app2->jsonSerialize(), $app1->jsonSerialize()], \JSON_THROW_ON_ERROR);

        $commandTester = $this->executeCommand($options);
        static::assertSame(0, $commandTester->getStatusCode());
        static::assertSame($json, trim($commandTester->getDisplay()));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with `--json` option
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testJsonOutput(): void
    {
        $entities = [
            $app1 = new AppEntity(),
            $app2 = new AppEntity(),
        ];

        $app1->setUniqueIdentifier('1');
        $app1->setName('Beta App');
        $app2->setUniqueIdentifier('2');
        $app2->setName('Alpha App');

        $this->appStorage
            ->expects($this->once())
            ->method('findAll')
            ->with(static::isInstanceOf(Context::class))
            ->willReturn(new AppCollection($entities));

        $options = ['--json' => true];
        $json = json_encode([$app2->jsonSerialize(), $app1->jsonSerialize()], \JSON_THROW_ON_ERROR);

        $commandTester = $this->executeCommand($options);
        static::assertSame(0, $commandTester->getStatusCode());
        static::assertSame($json, trim($commandTester->getDisplay()));
    }

    public function testInvalidFormatReturnsError(): void
    {
        $this->appStorage->expects($this->never())->method('findAll');
        $this->appStorage->expects($this->never())->method('findAllWithNameOrLabel');

        $commandTester = $this->executeCommand(['--format' => 'xml']);
        static::assertSame(2, $commandTester->getStatusCode());
        static::assertStringContainsString('Invalid format "xml"', $commandTester->getDisplay());
    }

    /**
     * @param array<string, bool|string> $options
     */
    private function executeCommand(array $options): CommandTester
    {
        $commandTester = new CommandTester($this->command);
        $commandTester->execute($options);

        return $commandTester;
    }
}
