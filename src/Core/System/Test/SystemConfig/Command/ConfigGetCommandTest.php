<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\SystemConfig\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\Command\ConfigGet;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigGetCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ConfigGet $configGetCommand;

    protected function setUp(): void
    {
        $this->configGetCommand = $this->getConfigGetCommand();
    }

    /**
     * @dataProvider configFormatJsonProvider
     */
    public function testConfigGetJson(string $key, string $format, string $output): void
    {
        $commandOutput = $this->executeCommand($key, $format);
        static::assertJsonStringEqualsJsonString($commandOutput, $output);
    }

    public function configFormatJsonProvider(): iterable
    {
        // config key, format, output
        yield 'test scalar value' => ['foo.bar.testBoolTrue', 'json', '{"foo.bar.testBoolTrue":true}'];
        yield 'test array' => ['foo.bar', 'json', '{"testBoolFalse":false,"testInt":123,"testBoolTrue":true,"testString":"test"}'];
        yield 'test array and json-pretty format' => ['foo.bar', 'json-pretty', '{"testBoolFalse":false,"testInt":123,"testBoolTrue":true,"testString":"test"}'];
    }

    /**
     * @dataProvider configFormatScalarProvider
     */
    public function testConfigGetScalar(string $key, string $output): void
    {
        $commandOutput = $this->executeCommand($key, 'scalar');
        static::assertEquals($commandOutput, $output);
    }

    public function configFormatScalarProvider(): iterable
    {
        // config key, output
        yield 'test string' => ['foo.bar.testString', 'test'];
        yield 'test true' => ['foo.bar.testBoolTrue', 'true'];
        yield 'test false' => ['foo.bar.testBoolFalse', 'false'];
        yield 'test int' => ['foo.bar.testInt', '123'];
    }

    /**
     * @dataProvider configFormatDefaultProvider
     */
    public function testConfigGetDefault(string $key, string $output): void
    {
        $commandOutput = $this->executeCommand($key);
        static::assertEquals($commandOutput, $output);
    }

    public function configFormatDefaultProvider(): iterable
    {
        // config key, output
        yield 'test 1D array' => ['foo.bar', "testBoolFalse => false\n  testBoolTrue => true\n  testInt => 123\n  testString => test"];
        yield 'test 1D array nested' => ['foo', "bar\n    testBoolFalse => false\n    testBoolTrue => true\n    testInt => 123\n    testString => test"];
        yield 'test single value true' => ['foo.bar.testBoolTrue', 'foo.bar.testBoolTrue => true'];
        yield 'test single value int' => ['foo.bar.testInt', 'foo.bar.testInt => 123'];
    }

    /**
     * @dataProvider configFormatLegacyProvider
     */
    public function testConfigGetLegacy(string $key, string $output): void
    {
        $commandOutput = $this->executeCommand($key, 'legacy');
        static::assertEquals(addslashes($commandOutput), addslashes($output));
    }

    public function configFormatLegacyProvider(): iterable
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
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);

        $systemConfigService->set('foo.bar.testString', 'test', TestDefaults::SALES_CHANNEL);
        $systemConfigService->set('foo.bar.testInt', 123, TestDefaults::SALES_CHANNEL);
        $systemConfigService->set('foo.bar.testBoolTrue', true, TestDefaults::SALES_CHANNEL);
        $systemConfigService->set('foo.bar.testBoolFalse', false, TestDefaults::SALES_CHANNEL);

        return new ConfigGet($systemConfigService);
    }
}
