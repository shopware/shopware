<?php declare(strict_types=1);

namespace Shopware\WebInstaller\Tests\Services;

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\WebInstaller\Services\ProjectComposerJsonUpdater;

/**
 * @internal
 */
#[CoversClass(ProjectComposerJsonUpdater::class)]
#[BackupGlobals(true)]
class ProjectComposerJsonUpdaterTest extends TestCase
{
    private string $json;

    protected function setUp(): void
    {
        $this->json = __DIR__ . '/composer.json';

        file_put_contents($this->json, json_encode([
            'require' => [
                'shopware/core' => '1.2.3',
            ],
        ], \JSON_THROW_ON_ERROR));
    }

    protected function tearDown(): void
    {
        unlink($this->json);
    }

    public function testUpdate(): void
    {
        ProjectComposerJsonUpdater::update(
            $this->json,
            '6.4.18.0'
        );

        $composerJson = json_decode((string) file_get_contents($this->json), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => '6.4.18.0',
                ],
            ],
            $composerJson
        );
    }

    public function testUpdateWithRC(): void
    {
        ProjectComposerJsonUpdater::update(
            $this->json,
            '6.4.18.0-rc1'
        );

        $composerJson = json_decode((string) file_get_contents($this->json), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => '6.4.18.0-rc1',
                ],
                'minimum-stability' => 'RC',
            ],
            $composerJson
        );
    }

    public function testUpdateWithFixVersion(): void
    {
        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '6.5.0.0';

        ProjectComposerJsonUpdater::update(
            $this->json,
            '6.4.18.0-rc1'
        );

        unset($_SERVER['SW_RECOVERY_NEXT_VERSION']);

        $composerJson = json_decode((string) file_get_contents($this->json), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => 'dev-trunk as 6.5.0.0',
                ],
                'minimum-stability' => 'RC',
            ],
            $composerJson
        );
    }

    public function testUpdateWithFixVersionAndBranch(): void
    {
        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '6.5.0.0';
        $_SERVER['SW_RECOVERY_NEXT_BRANCH'] = 'main';

        ProjectComposerJsonUpdater::update(
            $this->json,
            '6.4.18.0-rc1'
        );

        unset($_SERVER['SW_RECOVERY_NEXT_VERSION']);

        $composerJson = json_decode((string) file_get_contents($this->json), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => 'main as 6.5.0.0',
                ],
                'minimum-stability' => 'RC',
            ],
            $composerJson
        );
    }

    public function testUpdateWithFixVersionAndBranchSame(): void
    {
        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '6.5.0.0';
        $_SERVER['SW_RECOVERY_NEXT_BRANCH'] = '6.5.0.0';

        ProjectComposerJsonUpdater::update(
            $this->json,
            '6.4.18.0-rc1'
        );

        unset($_SERVER['SW_RECOVERY_NEXT_VERSION']);

        $composerJson = json_decode((string) file_get_contents($this->json), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => '6.5.0.0',
                ],
                'minimum-stability' => 'RC',
            ],
            $composerJson
        );
    }

    public function testUpdateWithSymfonyRuntimeRequirement(): void
    {
        file_put_contents($this->json, json_encode([
            'require' => [
                'shopware/core' => '1.2.3',
                'symfony/runtime' => '^5.0|^6.0',
            ],
        ], \JSON_THROW_ON_ERROR));

        ProjectComposerJsonUpdater::update(
            $this->json,
            '6.6.0.0'
        );

        $composerJson = json_decode((string) file_get_contents($this->json), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => '6.6.0.0',
                    'symfony/runtime' => '>=5',
                ],
            ],
            $composerJson
        );
    }

    public function testWithRecoveryRepository(): void
    {
        $_SERVER['SW_RECOVERY_NEXT_VERSION'] = '6.5.0.0';
        $_SERVER['SW_RECOVERY_NEXT_BRANCH'] = '6.5.0.0';

        $customRepo = [
            'type' => 'path',
            'url' => '/my/custom/repo',
            'options' => [
                'symlink' => true,
            ],
        ];
        $_SERVER['SW_RECOVERY_REPOSITORY'] = json_encode($customRepo);

        ProjectComposerJsonUpdater::update(
            $this->json,
            '6.4.18.0-rc1'
        );

        unset($_SERVER['SW_RECOVERY_NEXT_VERSION']);

        $composerJson = json_decode((string) file_get_contents($this->json), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(
            [
                'require' => [
                    'shopware/core' => '6.5.0.0',
                ],
                'minimum-stability' => 'RC',
                'repositories' => [
                    'recovery' => $customRepo,
                ],
            ],
            $composerJson
        );
    }
}
