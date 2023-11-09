<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Command\ValidateAppCommand;
use Shopware\Core\Framework\App\Validation\ManifestValidator;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
class ValidateAppCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testValidateApp(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->execute(['name' => 'withoutPermissions']);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertStringContainsString('[OK]', $commandTester->getDisplay());
    }

    public function testUsesAllAppFoldersFromAppDirIfMissingArgument(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->execute([]);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('[ERROR] The app "validationFailure" is invalid', $commandTester->getDisplay());
        static::assertStringContainsString('[ERROR] The app "validationFailures" is invalid', $commandTester->getDisplay());
    }

    private function createCommand(string $appFolder): ValidateAppCommand
    {
        return new ValidateAppCommand(
            $appFolder,
            $this->getContainer()->get(ManifestValidator::class)
        );
    }
}
