<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Command\CreateAppCommand;
use Shopware\Core\Framework\App\Lifecycle\RefreshableAppDryRun;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CreateAppCommand::class)]
class CreateAppCommandTest extends TestCase
{
    private const APP_DIR = '/non-existing/project';

    /**
     * @param array{
     *     name: string,
     *     label?: string,
     *     description?: string,
     *     version?: string
     * } $input
     */
    #[DataProvider('invalidInputProvider')]
    public function testCommandFailsWithInvalidInput(array $input, string $expectedMessage): void
    {
        $commandTester = $this->getCommandTester();

        $commandTester->execute($input);

        static::assertFileDoesNotExist(self::APP_DIR . '/TestApp/manifest.xml');

        static::assertStringContainsString(
            $expectedMessage,
            (string) preg_replace('/\s+/', ' ', trim($commandTester->getDisplay(true)))
        );
    }

    public static function invalidInputProvider(): \Generator
    {
        yield 'invalid_name' => [
            [
                'name' => 'smo',
            ],
            'The app name is too short (min 4 characters) or contains invalid characters',
        ];

        yield [
            [
                'name' => '%app%',
            ],
            'The app name is too short (min 4 characters) or contains invalid characters',
        ];

        yield [
            [
                'name' => 'app-',
            ],
            'The app name is too short (min 4 characters) or contains invalid characters',
        ];

        yield [
            [
                'name' => '1app',
            ],
            'The app name is too short (min 4 characters) or contains invalid characters',
        ];

        yield 'name with trailing newline' => [
            [
                'name' => "TestApp\n",
            ],
            'The app name is too short (min 4 characters) or contains invalid characters',
        ];

        yield [
            [
                'name' => 'my_app',
                'label' => '%nospecialchars$',
            ],
            'The app label contains invalid characters. Only alphanumerics and whitespaces are allowed.',
        ];

        yield [
            [
                'name' => 'my_app',
                'label' => 'My Awesome App',
                'description' => '%nospecialchars$',
            ],
            'The app description contains invalid characters. Only alphanumerics and whitespaces are allowed.',
        ];

        yield [
            [
                'name' => 'my_app',
                'label' => 'My Awesome App',
                'description' => 'My Awesome App',
                'version' => 'version1',
            ],
            'App version must be a valid Semver string.',
        ];

        yield [
            [
                'name' => 'my_app',
                'label' => 'My Awesome App',
                'description' => 'My Awesome App',
                'version' => '1.2',
            ],
            'App version must be a valid Semver string.',
        ];
    }

    private function getCommandTester(): CommandTester
    {
        $appCreateCommand = new CreateAppCommand(new RefreshableAppDryRun(), self::APP_DIR);

        $commandTester = new CommandTester($appCreateCommand);
        $application = new Application();
        $application->addCommand($appCreateCommand);

        return $commandTester;
    }
}
