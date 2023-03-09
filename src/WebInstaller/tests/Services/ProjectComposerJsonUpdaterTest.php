<?php declare(strict_types=1);

namespace App\Tests\Services;

use App\Services\ProjectComposerJsonUpdater;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @covers \App\Services\ProjectComposerJsonUpdater
 */
class ProjectComposerJsonUpdaterTest extends TestCase
{
    private string $json;

    public function setUp(): void
    {
        $this->json = __DIR__ . '/composer.json';

        file_put_contents($this->json, json_encode([
            'require' => [
                'shopware/core' => '1.2.3',
            ],
        ], \JSON_THROW_ON_ERROR));
    }

    public function tearDown(): void
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
                    'shopware/core' => '~6.4.0',
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
                    'shopware/core' => '~6.4.0',
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
}
