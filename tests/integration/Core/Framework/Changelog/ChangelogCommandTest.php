<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Changelog;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Changelog\Command\ChangelogChangeCommand;
use Shopware\Core\Framework\Changelog\Command\ChangelogCheckCommand;
use Shopware\Core\Framework\Changelog\Command\ChangelogReleaseCommand;
use Shopware\Core\Framework\Changelog\Processor\ChangelogReleaseCreator;
use Shopware\Core\Framework\Changelog\Processor\ChangelogReleaseExporter;
use Shopware\Core\Framework\Changelog\Processor\ChangelogValidator;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\NullOutput;

/**
 * @internal
 */
#[Group('skip-paratest')]
class ChangelogCommandTest extends TestCase
{
    use ChangelogTestBehaviour;
    use KernelTestBehaviour;

    /**
     * @return list<array{0: string, 1: list<string>}>
     */
    public static function provideCheckCommandFixtures(): array
    {
        return [
            [
                __DIR__ . '/_fixture/stage/command-invalid',
                [
                    '* Unknown flag _FLAG_ is assigned',
                    '[ERROR] You have 1 syntax errors in changelog files.',
                ],
            ],
            [
                __DIR__ . '/_fixture/stage/command-invalid-issue-number',
                [
                    '* The issue has an invalid format',
                    '[ERROR] You have 1 syntax errors in changelog files.',
                ],
            ],
            [
                __DIR__ . '/_fixture/stage/command-valid',
                [
                    '[OK] Done',
                ],
            ],
        ];
    }

    /**
     * @return list<array{0: string, 1: string|null, 2: list<string>}>
     */
    public static function provideChangeCommandFixtures(): array
    {
        return [
            [
                __DIR__ . '/_fixture/stage/command-invalid',
                \InvalidArgumentException::class,
                [
                ],
            ],
            [
                __DIR__ . '/_fixture/stage/command-valid',
                null,
                [
                    '# Core',
                    '* core',
                    '* Changed api',
                    '# API',
                    '* Deprecated admin',
                    '* list',
                    '# Storefront',
                    '* Added store',
                    '* Changed front',
                    '# Administration',
                    '* Deprecated admin',
                    '## UPGRADE',
                    '# Next Major Version Change',
                ],
            ],
        ];
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string|null, 3: array<string, list<string>>}>
     */
    public static function provideReleaseCommandFixtures(): array
    {
        return [
            'invalid-version' => [
                __DIR__ . '/_fixture/stage/command-invalid',
                '1.2',
                \RuntimeException::class,
                [
                ],
            ],
            'invalid-changelog' => [
                __DIR__ . '/_fixture/stage/command-invalid',
                '8.36.22.186',
                \InvalidArgumentException::class,
                [
                ],
            ],
            'valid-minor-release' => [
                __DIR__ . '/_fixture/stage/command-valid',
                '12.13.14.15',
                null,
                [
                    __DIR__ . '/_fixture/stage/command-valid/CHANGELOG.md' => [
                        '## 12.13.14.15',
                        '*  [NEXT-1111 - _TITLE_](./changelog/release-12-13-14-15/1977-12-10-a-full-change.md) ([_AUTHOR_](https://github.com/_GITHUB_))',
                    ],
                    __DIR__ . '/_fixture/stage/command-valid/UPGRADE-12.13.md' => [
                        '# 12.13.14.15',
                        '## UPGRADE',
                        '### THE INFORMATION',
                    ],
                    __DIR__ . '/_fixture/stage/command-valid/UPGRADE-12.14.md' => [
                        '# 12.14.0.0',
                        '## Introduced in 12.13.14.15',
                        '## DO THIS:',
                        '* FOO',
                    ],
                ],
            ],
            'valid-major-release' => [
                __DIR__ . '/_fixture/stage/command-valid-minor-update',
                '12.13.15.0',
                null,
                [
                    __DIR__ . '/_fixture/stage/command-valid-minor-update/CHANGELOG.md' => [
                        '## 12.13.14.15',
                        '## 12.13.15.0',
                        '*  [_ISSUE_ - _TITLE_](./changelog/release-12-13-14-15/1977-12-10-a-full-change.md) ([_AUTHOR_](https://github.com/_GITHUB_))',
                    ],
                    __DIR__ . '/_fixture/stage/command-valid-minor-update/UPGRADE-12.13.md' => [
                        '# 12.13.14.15',
                        '# 12.13.15.0',
                        '## UPGRADE, second',
                        '## UPGRADE, first',
                        '### THE INFORMATION',
                    ],
                    __DIR__ . '/_fixture/stage/command-valid-minor-update/UPGRADE-12.14.md' => [
                        '# 12.14.0.0',
                        '## Introduced in 12.13.15.0',
                        '## Introduced in 12.13.14.15',
                        '## DO THIS:',
                        '* FOO',
                        '## DO THAT:',
                        '* BAR',
                    ],
                ],
            ],
        ];
    }

    /**
     * @param list<string> $expectedOutputSnippets
     */
    #[DataProvider('provideCheckCommandFixtures')]
    public function testChangelogCheckCommand(string $path, array $expectedOutputSnippets): void
    {
        self::getContainer()->get(ChangelogValidator::class)->setPlatformRoot($path);
        $cmd = self::getContainer()->get(ChangelogCheckCommand::class);

        $output = new BufferedOutput();
        $cmd->run(new StringInput(''), $output);

        $outputContents = $output->fetch();

        foreach ($expectedOutputSnippets as $snippet) {
            static::assertStringContainsString($snippet, $outputContents);
        }
    }

    /**
     * @param class-string<\Throwable>|null $expectedException
     * @param list<string> $expectedOutputSnippets
     */
    #[DataProvider('provideChangeCommandFixtures')]
    public function testChangelogChangeCommand(string $path, ?string $expectedException, array $expectedOutputSnippets): void
    {
        self::getContainer()->get(ChangelogReleaseExporter::class)->setPlatformRoot($path);
        $cmd = self::getContainer()->get(ChangelogChangeCommand::class);

        $output = new BufferedOutput();

        if ($expectedException) {
            $this->expectException($expectedException);
        }

        $cmd->run(new StringInput(''), $output);

        $outputContents = $output->fetch();

        foreach ($expectedOutputSnippets as $snippet) {
            static::assertStringContainsString($snippet, $outputContents);
        }
    }

    /**
     * @param class-string<\Throwable>|null $expectedException
     * @param array<string, list<string>> $expectedFileContents
     */
    #[DataProvider('provideReleaseCommandFixtures')]
    public function testChangelogReleaseCommand(string $path, string $version, ?string $expectedException, array $expectedFileContents): void
    {
        self::getContainer()->get(ChangelogReleaseCreator::class)->setPlatformRoot($path);
        $cmd = self::getContainer()->get(ChangelogReleaseCommand::class);

        $output = new BufferedOutput();

        if ($expectedException) {
            $this->expectException($expectedException);
        }

        $cmd->run(new StringInput($version), $output);

        foreach ($expectedFileContents as $fileName => $expectedFileContent) {
            static::assertFileExists($fileName);
            $fileContents = (string) file_get_contents($fileName);

            foreach ($expectedFileContent as $line) {
                static::assertStringContainsString($line, $fileContents);
                static::assertSame(1, substr_count($fileContents, $line), \sprintf("Multiple occurrences of %s in \n %s", $line, $fileContents));
            }
        }
    }

    public function testChangelogReleaseWithFlags(): void
    {
        self::getContainer()->get(ChangelogReleaseCreator::class)->setPlatformRoot(__DIR__ . '/_fixture/stage/command-valid-flag');
        $cmd = self::getContainer()->get(ChangelogReleaseCommand::class);

        Feature::registerFeature('CHANGELOG-00001');
        Feature::registerFeature('CHANGELOG-00002');

        self::getContainer()->get(ChangelogReleaseCreator::class)->setActiveFlags([
            'CHANGELOG-00001' => [
                'default' => true,
            ],
        ]);

        $cmd->run(new StringInput('12.13.14.15'), new NullOutput());

        static::assertFileExists(__DIR__ . '/_fixture/stage/command-valid-flag/CHANGELOG.md');
        $content = (string) file_get_contents(__DIR__ . '/_fixture/stage/command-valid-flag/CHANGELOG.md');

        static::assertStringContainsString('/changelog/release-12-13-14-15/1977-12-10-a-full-change.md', $content);
        static::assertStringContainsString('/changelog/release-12-13-14-15/1977-12-11-flag-active', $content);
        static::assertStringNotContainsString('/changelog/release-12-13-14-15/1977-12-11-flag-inactive', $content);
    }
}
