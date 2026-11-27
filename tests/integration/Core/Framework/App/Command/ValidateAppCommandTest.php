<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Command\ValidateAppCommand;
use Shopware\Core\Framework\App\Validation\AppValidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
class ValidateAppCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testValidateApp(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->execute(['name' => 'withoutPermissions']);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertSame('✔ 1 app valid', trim($commandTester->getDisplay()));
    }

    public function testAppWithOnlyNoticesIsValid(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->execute(['name' => 'validationFailure']);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertSame(
            "validationFailure\n  notice  Missing label translations: de-DE\n\n✔ 0 errors, 1 notice",
            trim($commandTester->getDisplay())
        );
    }

    public function testUsesAllAppFoldersFromAppDirIfMissingArgument(): void
    {
        $commandTester = new CommandTester($this->createCommand(__DIR__ . '/_fixtures'));
        $commandTester->execute([]);

        $display = $commandTester->getDisplay();

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString(
            "validationFailure\n  notice  Missing label translations: de-DE\n",
            $display
        );
        static::assertStringContainsString(
            "validationFailures\n"
            . "  notice  Webhooks not hookable: hook4NotAllowed (tax.written)\n"
            . "  error   Missing permissions: order:read, product:read\n",
            $display
        );
        static::assertStringEndsWith("✖ 1 error, 2 notices (1 of 6 apps invalid)\n", $display);
    }

    private function createCommand(string $appFolder): ValidateAppCommand
    {
        return new ValidateAppCommand(
            $appFolder,
            static::getContainer()->get(AppValidator::class)
        );
    }
}
