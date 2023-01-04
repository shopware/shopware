<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\System\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Maintenance\System\Command\SystemInstallCommand;
use Shopware\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Maintenance\System\Command\SystemInstallCommand
 */
class SystemInstallCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        @unlink(__DIR__ . '/install.lock');
    }

    /**
     * @param array<string, mixed> $mockInputValues
     *
     * @dataProvider dataProviderTestExecuteWhenInstallLockExists
     */
    public function testExecuteWhenInstallLockExists(array $mockInputValues): void
    {
        touch(__DIR__ . '/install.lock');

        $systemInstallCmd = $this->prepareCommandInstance();

        $refMethod = ReflectionHelper::getMethod(SystemInstallCommand::class, 'execute');

        $result = $refMethod->invoke($systemInstallCmd, $this->getMockInput($mockInputValues), $this->getMockOutput());

        static::assertEquals($result, Command::FAILURE);
    }

    public function dataProviderTestExecuteWhenInstallLockExists(): \Generator
    {
        yield 'Data provider for test execute failure' => [
            'Mock method getOption from input' => [
                'force' => false,
                'shopName' => 'Storefront',
                'shopEmail' => 'admin@gmail.com',
                'shopLocale' => 'de-DE',
                'shopCurrency' => 'USD',
                'skipJwtKeysGeneration' => true,
                'basicSetup' => true,
                'shopName_1' => 'Storefront',
                'shopLocale_1' => 'de-DE',
                'no-assign-theme' => true,
                'dropDatabase' => true,
                'createDatabase' => true,
            ],
        ];
    }

    private function prepareCommandInstance(): SystemInstallCommand
    {
        $setupDatabaseAdapterMock = $this->createMock(SetupDatabaseAdapter::class);
        $systemInstallCmd = new SystemInstallCommand(__DIR__, $setupDatabaseAdapterMock);

        $appMock = $this->createMock(Application::class);
        $appMock->method('has')
            ->willReturn(true);

        $mockCommand = $this->getMockBuilder(Command::class)
            ->disableOriginalConstructor()
            ->getMock();

        $mockCommand->method('run')
            ->willReturn(0);

        $inputDefinitionMock = $this->createMock(InputDefinition::class);
        $inputDefinitionMock->method('hasArgument')
            ->willReturn(true);
        $inputDefinitionMock->method('hasNegation')
            ->willReturn(true);

        $mockCommand->method('getDefinition')
            ->willReturn($inputDefinitionMock);

        $appMock->method('find')
            ->willReturn($mockCommand);
        $appMock->method('getHelperSet')
            ->willReturn($this->createMock(HelperSet::class));

        $systemInstallCmd->setApplication($appMock);

        return $systemInstallCmd;
    }

    /**
     * @param array<string, mixed> $mockInputValues
     */
    private function getMockInput(array $mockInputValues): InputInterface
    {
        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')
            ->willReturnOnConsecutiveCalls(...array_values($mockInputValues));

        return $input;
    }

    private function getMockOutput(): OutputInterface
    {
        $outputFormatterMock = $this->createMock(OutputFormatterInterface::class);
        $outputFormatterMock->method('isDecorated')
            ->willReturn(false);

        $outputMock = $this->createMock(OutputInterface::class);
        $outputMock->method('getFormatter')
            ->willReturn($outputFormatterMock);

        return $outputMock;
    }
}
