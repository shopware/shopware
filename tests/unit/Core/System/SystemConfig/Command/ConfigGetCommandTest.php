<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SystemConfig\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Command\ConfigGet;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(ConfigGet::class)]
class ConfigGetCommandTest extends TestCase
{
    private ConfigGet $configGetCommand;

    protected function setUp(): void
    {
        $this->configGetCommand = $this->getConfigGetCommand();
    }

    #[DataProvider('configFormatJsonProvider')]
    public function testConfigGetJson(string $key, string $format, string $output): void
    {
        $commandOutput = $this->executeCommand($key, $format);
        static::assertJsonStringEqualsJsonString($commandOutput, $output);
    }

    public static function configFormatJsonProvider(): \Generator
    {
        // config key, format, output
        yield 'test scalar value' => ['foo.bar.testBoolTrue', 'json', '{"foo.bar.testBoolTrue":true}'];
        yield 'test array' => ['foo.bar', 'json', '{"testBoolFalse":false,"testInt":123,"testBoolTrue":true,"testString":"test"}'];
        yield 'test array and json-pretty format' => ['foo.bar', 'json-pretty', '{"testBoolFalse":false,"testInt":123,"testBoolTrue":true,"testString":"test"}'];
    }

    #[DataProvider('configFormatScalarProvider')]
    public function testConfigGetScalar(string $key, string $output): void
    {
        $commandOutput = $this->executeCommand($key, 'scalar');
        static::assertEquals($commandOutput, $output);
    }

    public static function configFormatScalarProvider(): \Generator
    {
        // config key, output
        yield 'test string' => ['foo.bar.testString', 'test'];
        yield 'test true' => ['foo.bar.testBoolTrue', 'true'];
        yield 'test false' => ['foo.bar.testBoolFalse', 'false'];
        yield 'test int' => ['foo.bar.testInt', '123'];
    }

    #[DataProvider('configFormatDefaultProvider')]
    public function testConfigGetDefault(string $key, string $output): void
    {
        $commandOutput = $this->executeCommand($key);
        static::assertEquals($commandOutput, $output);
    }

    public static function configFormatDefaultProvider(): \Generator
    {
        // config key, output
        yield 'test 1D array' => ['foo.bar', "testBoolFalse => false\n  testBoolTrue => true\n  testInt => 123\n  testString => test"];
        yield 'test 1D array nested' => ['foo', "bar\n    testBoolFalse => false\n    testBoolTrue => true\n    testInt => 123\n    testString => test"];
        yield 'test single value true' => ['foo.bar.testBoolTrue', 'foo.bar.testBoolTrue => true'];
        yield 'test single value int' => ['foo.bar.testInt', 'foo.bar.testInt => 123'];
    }

    #[DataProvider('configFormatLegacyProvider')]
    public function testConfigGetLegacy(string $key, string $output): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $commandOutput = $this->executeCommand($key, 'legacy');
        static::assertEquals(addslashes($commandOutput), addslashes($output));
    }

    public static function configFormatLegacyProvider(): \Generator
    {
        // config key, output
        yield 'test 1d array value' => ['foo.bar', "1\n123\ntest"];
        yield 'test true' => ['foo.bar.testBoolTrue', '1'];
        yield 'test false' => ['foo.bar.testBoolFalse', ''];
        yield 'test int' => ['foo.bar.testInt', '123'];
    }

    private function executeCommand(string $key, string $format = 'default'): string
    {
        $commandTester = new CommandTester($this->configGetCommand);

        $commandTester->execute([
            'key' => $key,
            '--salesChannelId' => TestDefaults::SALES_CHANNEL,
            '--format' => $format,
        ]);

        return trim($commandTester->getDisplay());
    }

    private function getConfigGetCommand(): ConfigGet
    {
        $systemConfigService = new StaticSystemConfigService([]);

        $systemConfigService->set('foo.bar.testString', 'test', TestDefaults::SALES_CHANNEL);
        $systemConfigService->set('foo.bar.testInt', 123, TestDefaults::SALES_CHANNEL);
        $systemConfigService->set('foo.bar.testBoolTrue', true, TestDefaults::SALES_CHANNEL);
        $systemConfigService->set('foo.bar.testBoolFalse', false, TestDefaults::SALES_CHANNEL);

        return new ConfigGet($systemConfigService);
    }
}
