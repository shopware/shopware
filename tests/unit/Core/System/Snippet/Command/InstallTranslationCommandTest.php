<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\Command;

use GuzzleHttp\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\InstallTranslationCommand;
use Shopware\Core\System\Snippet\DataTransfer\Language\LanguageCollection;
use Shopware\Core\System\Snippet\DataTransfer\PluginMapping\PluginMappingCollection;
use Shopware\Core\System\Snippet\Service\TranslationLoader;
use Shopware\Core\System\Snippet\SnippetException;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(InstallTranslationCommand::class)]
class InstallTranslationCommandTest extends TestCase
{
    private TranslationLoader&MockObject $translationLoader;

    private TranslationConfig $config;

    protected function setUp(): void
    {
        $this->translationLoader = $this->createMock(TranslationLoader::class);
        $this->config = new TranslationConfig(
            new Uri('http://localhost:8000'),
            ['en-GB', 'es-ES'],
            [],
            new LanguageCollection(),
            new PluginMappingCollection()
        );
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
            ->willReturnCallback(function (string $locale, Context $context, bool $activate): void {
                $expectedLocales = ['en-GB', 'es-ES'];

                static::assertTrue(\in_array($locale, $expectedLocales, true));
                static::assertTrue($activate, 'Default should activate when --skip-activation is not provided');
            });

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--locales' => 'en-GB,es-ES']);
        $tester->assertCommandIsSuccessful();
    }

    public function testExecuteRunsSuccessfulWithSkipActivation(): void
    {
        $this->translationLoader
            ->expects($this->once())
            ->method('load')
            ->willReturnCallback(function (string $locale, Context $context, bool $activate): void {
                static::assertSame('en-GB', $locale);
                static::assertFalse($activate, 'Should pass activate=false when --skip-activation is used');
            });

        $command = $this->getCommand();
        $tester = new CommandTester($command);

        $tester->execute(['--locales' => 'en-GB', '--skip-activation' => true]);
        $tester->assertCommandIsSuccessful();
    }

    private function getCommand(): InstallTranslationCommand
    {
        return new InstallTranslationCommand($this->translationLoader, $this->config);
    }
}
