<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\ValidateSnippetsCommand;
use Shopware\Core\System\Snippet\Files\GenericSnippetFile;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\SnippetFileHandler;
use Shopware\Core\System\Snippet\SnippetFixer;
use Shopware\Core\System\Snippet\SnippetValidator;
use Shopware\Core\System\Snippet\Struct\MissingSnippetCollection;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\HelperSet;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ValidateSnippetsCommand::class)]
class ValidateSnippetsCommandTest extends TestCase
{
    #[TestDox('Complete snippets are reported as valid')]
    public function testReportsValidSnippets(): void
    {
        $commandTester = $this->createCommandTester(new SnippetFileCollection(), []);

        static::assertSame(Command::SUCCESS, $commandTester->execute([]));
        static::assertStringContainsString('Snippets are valid!', $commandTester->getDisplay());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    #[TestDox('The deprecated command alias remains supported before v6.8')]
    public function testDeprecatedCommandAliasRemainsSupported(): void
    {
        $command = $this->createCommand(new SnippetFileCollection(), []);
        $command->setName('translation:validate');
        $command->setAliases(['snippets:validate']);
        $application = new Application();
        // without this, Application::run() calls exit(0) and silently kills the whole PHPUnit process
        $application->setAutoExit(false);
        $application->addCommand($command);
        $applicationTester = new ApplicationTester($application);

        static::assertSame(Command::SUCCESS, $applicationTester->run(['command' => 'snippets:validate']));
    }

    #[TestDox('The deprecated command alias stays silent when v6.8 is active')]
    public function testDeprecatedCommandAliasIsSilent(): void
    {
        $command = $this->createCommand(new SnippetFileCollection(), []);
        $command->setName('translation:validate');
        $command->setAliases(['snippets:validate']);
        $application = new Application();
        $application->setAutoExit(false);
        $application->addCommand($command);
        // without catching, a deprecation wrongly raised as FeatureException would surface here
        $application->setCatchExceptions(false);
        $applicationTester = new ApplicationTester($application);

        static::assertSame(Command::SUCCESS, $applicationTester->run(['command' => 'snippets:validate']));
    }

    #[TestDox('Missing translations are listed per ISO and fail the command')]
    public function testReportsMissingSnippets(): void
    {
        [$collection, $jsonByPath] = $this->createIncompleteSnippetSetup();
        $snippetFixer = $this->createMock(SnippetFixer::class);
        $snippetFixer->expects($this->never())->method('fix');

        $commandTester = $this->createCommandTester($collection, $jsonByPath, $snippetFixer);

        static::assertSame(-1, $commandTester->execute([]));

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('Invalid snippets found!', $display);
        static::assertStringContainsString('checkout.finish', $display);
        static::assertStringContainsString('de-DE', $display);
    }

    #[TestDox('The fix wizard asks for the missing translation and passes it to the fixer')]
    public function testFixWizardCollectsTranslations(): void
    {
        [$collection, $jsonByPath] = $this->createIncompleteSnippetSetup();
        $snippetFixer = $this->createMock(SnippetFixer::class);
        $snippetFixer
            ->expects($this->once())
            ->method('fix')
            ->willReturnCallback(function (MissingSnippetCollection $missingSnippets): void {
                $this->assertCount(1, $missingSnippets);
                $this->assertSame('Kasse', $missingSnippets->first()?->getTranslation());
            });

        $commandTester = $this->createCommandTester($collection, $jsonByPath, $snippetFixer);
        $commandTester->setInputs(['Kasse']);

        static::assertSame(Command::SUCCESS, $commandTester->execute(['--fix' => true]));
    }

    /**
     * One english snippet file with a translation the german file is missing.
     *
     * @return array{0: SnippetFileCollection, 1: array<string, array<string, mixed>>}
     */
    private function createIncompleteSnippetSetup(): array
    {
        $collection = new SnippetFileCollection([
            $this->createSnippetFile('en-GB', '/snippets/storefront.en-GB.json'),
            $this->createSnippetFile('de-DE', '/snippets/storefront.de-DE.json'),
        ]);

        $jsonByPath = [
            '/snippets/storefront.en-GB.json' => ['checkout' => ['finish' => 'Checkout']],
            '/snippets/storefront.de-DE.json' => [],
        ];

        return [$collection, $jsonByPath];
    }

    /**
     * @param array<string, array<string, mixed>> $jsonByPath
     */
    private function createCommandTester(
        SnippetFileCollection $collection,
        array $jsonByPath,
        ?SnippetFixer $snippetFixer = null
    ): CommandTester {
        return new CommandTester($this->createCommand($collection, $jsonByPath, $snippetFixer));
    }

    /**
     * @param array<string, array<string, mixed>> $jsonByPath
     */
    private function createCommand(
        SnippetFileCollection $collection,
        array $jsonByPath,
        ?SnippetFixer $snippetFixer = null
    ): ValidateSnippetsCommand {
        $snippetFileHandler = static::createStub(SnippetFileHandler::class);
        $snippetFileHandler->method('findAdministrationSnippetFiles')->willReturn([]);
        $snippetFileHandler->method('findStorefrontSnippetFiles')->willReturn([]);
        $snippetFileHandler
            ->method('openJsonFile')
            ->willReturnCallback(static fn (string $path): array => $jsonByPath[$path] ?? []);

        $command = new ValidateSnippetsCommand(
            new SnippetValidator($collection, $snippetFileHandler, '/project'),
            $snippetFixer ?? static::createStub(SnippetFixer::class)
        );
        $command->setHelperSet(new HelperSet([new QuestionHelper()]));

        return $command;
    }

    private function createSnippetFile(string $iso, string $path): GenericSnippetFile
    {
        return new GenericSnippetFile(
            'storefront.' . $iso,
            $path,
            $iso,
            'shopware AG',
            true,
            'Storefront'
        );
    }
}
