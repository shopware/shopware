<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Command\ConfigSet;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ConfigSet::class)]
class ConfigSetCommandTest extends TestCase
{
    private ConfigSet $configSetCommand;

    private SystemConfigService&MockObject $systemConfigService;

    protected function setUp(): void
    {
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->configSetCommand = new ConfigSet($this->systemConfigService);
    }

    /**
     * @param array<string, mixed> $input
     */
    #[DataProvider('configSetProvider')]
    public function testConfigSet(array $input, string $expectedKey, mixed $expectedValue, ?string $expectedSalesChannelId, bool $expectedSilent): void
    {
        $this->systemConfigService->expects($this->once())
            ->method('set')
            ->with($expectedKey, static::identicalTo($expectedValue), $expectedSalesChannelId, $expectedSilent);

        $commandTester = new CommandTester($this->configSetCommand);
        $commandTester->execute($input);
    }

    public static function configSetProvider(): \Generator
    {
        yield 'string false' => [
            'input' => ['key' => 'my.key', 'value' => 'false', '--salesChannelId' => TestDefaults::SALES_CHANNEL],
            'expectedKey' => 'my.key',
            'expectedValue' => 'false',
            'expectedSalesChannelId' => TestDefaults::SALES_CHANNEL,
            'expectedSilent' => false,
        ];

        yield 'json decoded false' => [
            'input' => ['key' => 'my.key', 'value' => 'false', '--json' => true, '--salesChannelId' => TestDefaults::SALES_CHANNEL],
            'expectedKey' => 'my.key',
            'expectedValue' => false,
            'expectedSalesChannelId' => TestDefaults::SALES_CHANNEL,
            'expectedSilent' => false,
        ];

        yield 'string int' => [
            'input' => ['key' => 'my.key', 'value' => '4'],
            'expectedKey' => 'my.key',
            'expectedValue' => '4',
            'expectedSalesChannelId' => null,
            'expectedSilent' => false,
        ];

        yield 'json decoded int' => [
            'input' => ['key' => 'my.key', 'value' => '5', '--json' => true],
            'expectedKey' => 'my.key',
            'expectedValue' => 5,
            'expectedSalesChannelId' => null,
            'expectedSilent' => false,
        ];

        yield 'string float' => [
            'input' => ['key' => 'my.key', 'value' => '2.2'],
            'expectedKey' => 'my.key',
            'expectedValue' => '2.2',
            'expectedSalesChannelId' => null,
            'expectedSilent' => false,
        ];

        yield 'json decoded float' => [
            'input' => ['key' => 'my.key', 'value' => '3.3', '--json' => true],
            'expectedKey' => 'my.key',
            'expectedValue' => 3.3,
            'expectedSalesChannelId' => null,
            'expectedSilent' => false,
        ];

        yield 'string json' => [
            'input' => ['key' => 'my.key', 'value' => '{"name":"abc","place":"xyz"}'],
            'expectedKey' => 'my.key',
            'expectedValue' => '{"name":"abc","place":"xyz"}',
            'expectedSalesChannelId' => null,
            'expectedSilent' => false,
        ];

        yield 'json decoded object' => [
            'input' => ['key' => 'my.key', 'value' => '{"name":"abc","place":"xyz"}', '--json' => true],
            'expectedKey' => 'my.key',
            'expectedValue' => ['name' => 'abc', 'place' => 'xyz'],
            'expectedSalesChannelId' => null,
            'expectedSilent' => false,
        ];

        yield 'json decoded non-json string remains string' => [
            'input' => ['key' => 'my.key', 'value' => 'random string', '--json' => true],
            'expectedKey' => 'my.key',
            'expectedValue' => 'random string',
            'expectedSalesChannelId' => null,
            'expectedSilent' => false,
        ];

        yield 'silent flag' => [
            'input' => ['key' => 'my.key', 'value' => 'value', '--silent' => true],
            'expectedKey' => 'my.key',
            'expectedValue' => 'value',
            'expectedSalesChannelId' => null,
            'expectedSilent' => true,
        ];
    }
}
