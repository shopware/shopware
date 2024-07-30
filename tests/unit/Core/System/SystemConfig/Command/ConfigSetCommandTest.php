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
#[Package('services-settings')]
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

    public static function configValueProvider(): \Generator
    {
        /* value, expected_value, decode */
        yield 'String false' => ['false', 'false', false];
        yield 'Decode string false' => ['false', false, true];
        yield 'String int' => ['4', '4', false];
        yield 'Decode String int' => ['5', 5, true];
        yield 'String float' => ['2.2', '2.2', false];
        yield 'Decode String float' => ['3.3', 3.3, true];
        yield 'String json' => [
            '{"name":"abc","place":"xyz"}',
            '{"name":"abc","place":"xyz"}',
            false,
        ];
        yield 'Decode String json' => [
            '{"name":"abc","place":"xyz"}',
            ['name' => 'abc', 'place' => 'xyz'],
            true,
        ];
        yield 'Decode string remains string' => ['random string', 'random string', true];
    }

    /**
     * @param string $expectedValue
     */
    #[DataProvider('configValueProvider')]
    public function testConfigSetValue(string $value, $expectedValue, bool $json = false): void
    {
        $key = 'fake_config_key';

        $this->systemConfigService->expects(static::once())
            ->method('set')
            ->with(
                $key,
                static::identicalTo($expectedValue),
                TestDefaults::SALES_CHANNEL
            );

        $commandTester = new CommandTester($this->configSetCommand);
        $command = [
            'key' => $key,
            'value' => $value,
            '--salesChannelId' => TestDefaults::SALES_CHANNEL,
        ];

        if ($json) {
            $command['--json'] = true;
        }

        $commandTester->execute($command);
    }
}
