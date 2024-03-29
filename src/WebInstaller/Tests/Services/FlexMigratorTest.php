<?php
declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Services;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Services\FlexMigrator;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[CoversClass(FlexMigrator::class)]
class FlexMigratorTest extends TestCase
{
    public function testCleanup(): void
    {
        $tmpDir = sys_get_temp_dir() . '/flex-migrator-test';
        $fs = new Filesystem();
        $fs->mkdir($tmpDir);

        $fs->touch($tmpDir . '/Dockerfile');
        $fs->mkdir($tmpDir . '/.github/workflows');
        $fs->touch($tmpDir . '/.github/workflows/build.yml');

        $flexMigrator = new FlexMigrator();

        $flexMigrator->cleanup($tmpDir);

        static::assertFileDoesNotExist($tmpDir . '/Dockerfile');
        static::assertFileDoesNotExist($tmpDir . '/.github/workflows/build.yml');

        $fs->remove($tmpDir);
    }

    public function testCopyTemplateFiles(): void
    {
        $tmpDir = sys_get_temp_dir() . '/flex-migrator-test';
        $fs = new Filesystem();
        $fs->mkdir($tmpDir);

        $flexMigrator = new FlexMigrator();

        $flexMigrator->copyNewTemplateFiles($tmpDir);

        static::assertFileExists($tmpDir . '/symfony.lock');
        static::assertFileExists($tmpDir . '/bin/console');

        $fs->remove($tmpDir);
    }

    public function testMigrateEnv(): void
    {
        $tmpDir = sys_get_temp_dir() . '/flex-migrator-test';
        $fs = new Filesystem();
        $fs->mkdir($tmpDir);

        $flexMigrator = new FlexMigrator();

        $flexMigrator->migrateEnvFile($tmpDir);

        static::assertFileExists($tmpDir . '/.env');
        static::assertStringContainsString('###> symfony/lock ###', (string) file_get_contents($tmpDir . '/.env'));

        $fs->remove($tmpDir);
    }

    public function testMigrateEnvExistingEnv(): void
    {
        $tmpDir = sys_get_temp_dir() . '/flex-migrator-test';
        $fs = new Filesystem();
        $fs->mkdir($tmpDir);
        $fs->dumpFile($tmpDir . '/.env', 'old');

        $flexMigrator = new FlexMigrator();

        $flexMigrator->migrateEnvFile($tmpDir);

        static::assertFileExists($tmpDir . '/.env');
        static::assertFileExists($tmpDir . '/.env.local');
        static::assertStringContainsString('###> symfony/lock ###', (string) file_get_contents($tmpDir . '/.env'));
        static::assertSame('old', (string) file_get_contents($tmpDir . '/.env.local'));

        $fs->remove($tmpDir);
    }

    public static function composerCases(): \Generator
    {
        yield 'no repos' => [
            [
                'require' => [
                    'shopware/recovery' => '4.4.*',
                ],
            ],
        ];

        yield 'repos empty' => [
            [
                'require' => [
                    'shopware/recovery' => '4.4.*',
                ],
                'repositories' => [],
            ],
        ];

        yield 'repos only one repo' => [
            [
                'require' => [
                    'shopware/recovery' => '4.4.*',
                ],
                'repositories' => [
                    [
                        'type' => 'path',
                        'url' => 'custom/plugins/*',
                        'options' => [
                            'symlink' => true,
                        ],
                    ],
                ],
            ],
        ];

        yield 'repos merged' => [
            [
                'require' => [
                    'shopware/recovery' => '4.4.*',
                ],
                'repositories' => [
                    [
                        'type' => 'path',
                        'url' => 'custom/plugins/*',
                        'options' => [
                            'symlink' => true,
                        ],
                    ],
                    [
                        'type' => 'path',
                        'url' => 'custom/plugins/*/packages/*',
                        'options' => [
                            'symlink' => true,
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param array<mixed> $composer
     */
    #[DataProvider('composerCases')]
    public function testPatchComposerJson(array $composer): void
    {
        $tmpDir = sys_get_temp_dir() . '/flex-migrator-test';
        $fs = new Filesystem();
        $fs->mkdir($tmpDir);
        $fs->dumpFile($tmpDir . '/composer.json', json_encode($composer, \JSON_THROW_ON_ERROR));

        $flexMigrator = new FlexMigrator();

        $flexMigrator->patchRootComposerJson($tmpDir);

        $composerJson = json_decode((string) file_get_contents($tmpDir . '/composer.json'), true);

        static::assertEquals(
            [
                'require' => [
                    'symfony/flex' => '^2',
                ],
                'config' => [
                    'allow-plugins' => [
                        'symfony/flex' => true,
                        'symfony/runtime' => true,
                    ],
                ],
                'scripts' => [
                    'auto-scripts' => [
                        'assets:install' => 'symfony-cmd',
                    ],
                    'post-install-cmd' => [
                        '@auto-scripts',
                    ],
                    'post-update-cmd' => [
                        '@auto-scripts',
                    ],
                ],
                'extra' => [
                    'symfony' => [
                        'allow-contrib' => true,
                        'endpoint' => [
                            'https://raw.githubusercontent.com/shopware/recipes/flex/main/index.json',
                            'flex://defaults',
                        ],
                    ],
                ],
                'require-dev' => [
                    'shopware/dev-tools' => '*',
                ],
                'repositories' => [
                    [
                        'type' => 'path',
                        'url' => 'custom/plugins/*',
                        'options' => [
                            'symlink' => true,
                        ],
                    ],
                    [
                        'type' => 'path',
                        'url' => 'custom/plugins/*/packages/*',
                        'options' => [
                            'symlink' => true,
                        ],
                    ],
                ],
            ],
            $composerJson
        );

        $fs->remove($tmpDir);
    }
}
