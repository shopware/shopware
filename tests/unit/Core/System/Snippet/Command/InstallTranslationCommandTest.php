<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\InstallTranslationCommand;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\SnippetException;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(InstallTranslationCommand::class)]
class InstallTranslationCommandTest extends TestCase
{
    private TranslationLoader&MockObject $translationLoader;

    protected function setUp(): void
    {
        $this->translationLoader = $this->createMock(TranslationLoader::class);
    }

    public function testExecuteThrowsExceptionWithoutArguments(): void
    {
        $command = $this->getCommand();
        $tester = new CommandTester($command);

        static::expectException(SnippetException::class);
        static::expectExceptionMessage('You must specify either --all or --locales to run the InstallTranslationCommand.');
        $tester->execute([]);
    }

    public function testExecuteThrowsExceptionWithInvalidLocales(): void
    {
        $command = $this->getCommand();
        $tester = new CommandTester($command);

        static::expectException(SnippetException::class);
        $tester->execute(['--locales' => 'invalid-locale']);
    }

    public function testExecuteTranslationCommandRunsSuccessful(): void
    {
        $this->translationLoader->expects($this->exactly(2))
            ->method('load')
            ->willReturnCallback(function (string $locale): void {
                $expectedLocales = ['en-GB', 'es-ES'];

                static::assertTrue(\in_array($locale, $expectedLocales, true));
            });

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--locales' => 'en-GB,es-ES']);
        $tester->assertCommandIsSuccessful();
    }

    private function getCommand(): InstallTranslationCommand
    {
        return new InstallTranslationCommand($this->translationLoader);
    }
}
