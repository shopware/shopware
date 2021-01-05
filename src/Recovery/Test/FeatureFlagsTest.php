<?php declare(strict_types=1);

namespace Shopware\Recovery\Test;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Shopware\Core\Framework\Feature;
use Slim\App;
use Slim\Http\Environment;
use Slim\Http\Request;

class FeatureFlagsTest extends TestCase
{
    /**
     * @var \Slim\App
     */
    private $installApp;

    /**
     * @var \Slim\App
     */
    private $updateApp;

    public function setUp(): void
    {
        unset(
            $_SERVER['FEATURE_NEXT_101'],
            $_SERVER['FEATURE_ALL']
        );
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        /* @var \Slim\App $app */
        $this->installApp = require __DIR__ . '/../Install/src/app.php';
        $this->prepareTestApp($this->installApp, '/recovery/install/index.php');

        /* @var \Slim\App $app */
        $this->updateApp = require __DIR__ . '/../Update/src/app.php';
        $this->prepareTestApp($this->updateApp, '/recovery/update/index.php');

        if (!\defined('UPDATE_IS_MANUAL')) {
            \define('UPDATE_IS_MANUAL', true);
            \define('UPDATE_FILES_PATH', null);
            \define('UPDATE_ASSET_PATH', __DIR__ . '/_update-assets');
            \define('UPDATE_META_FILE', null);
        }
    }

    /**
     * @dataProvider featureFlagProvider
     */
    public function testInstallApp(array $env, bool $inEnvFile, int $expectedStatusCode): void
    {
        $this->prepareEnv($this->installApp, $env, $inEnvFile);

        $response = $this->installApp->run();

        static::assertSame($expectedStatusCode, $response->getStatusCode());
    }

    /**
     * @dataProvider featureFlagProvider
     */
    public function testUpdateApp(array $env, bool $inEnvFile, int $expectedStatusCode): void
    {
        $this->prepareEnv($this->updateApp, $env, $inEnvFile);

        $response = $this->updateApp->run();

        static::assertSame($expectedStatusCode, $response->getStatusCode());
    }

    public function featureFlagProvider(): \Generator
    {
        yield 'no-feature' => [
            [],
            false,
            400,
        ];

        yield 'disabled-feature-all' => [
            ['FEATURE_ALL' => ''],
            false,
            400,
        ];

        yield 'feature-empty' => [
            ['FEATURE_NEXT_101' => ''],
            false,
            400,
        ];

        yield 'feature-active' => [
            ['FEATURE_NEXT_101' => 'true'],
            false,
            200,
        ];

        yield 'feature-all-minor' => [
            ['FEATURE_ALL' => 'minor'],
            false,
            200,
        ];

        yield 'feature-all-major' => [
            ['FEATURE_ALL' => 'major'],
            false,
            200,
        ];

        yield 'disabled-feature-all-in-env-file' => [
            ['FEATURE_ALL' => ''],
            true,
            400,
        ];

        yield 'feature-empty-in-env-file' => [
            ['FEATURE_NEXT_101' => ''],
            true,
            400,
        ];

        yield 'feature-active-in-env-file' => [
            ['FEATURE_NEXT_101' => 'true'],
            true,
            200,
        ];

        yield 'feature-all-minor-in-env-file' => [
            ['FEATURE_ALL' => 'minor'],
            true,
            200,
        ];

        yield 'feature-all-major-in-env-file' => [
            ['FEATURE_ALL' => 'major'],
            true,
            200,
        ];
    }

    private function prepareEnv(App $app, array $env, bool $withEnvFile = false): void
    {
        if ($withEnvFile) {
            $tmpEnvFile = tempnam(sys_get_temp_dir(), 'swtestenv');
            $content = '';
            foreach ($env as $key => $value) {
                $content .= sprintf('%s=%s', $key, $value) . \PHP_EOL;
            }
            file_put_contents($tmpEnvFile, $content);

            /** @var \Slim\Container $container */
            $container = $app->getContainer();
            $container->offsetSet('env.path', $tmpEnvFile);
        } else {
            foreach ($env as $key => $value) {
                $_SERVER[$key] = $value;
            }
        }
    }

    private function prepareTestApp(App $app, string $scriptName): void
    {
        $env = new Environment([
            'REQUEST_METHOD' => 'GET',
            'SERVER_NAME' => 'localhost',
            'SCRIPT_NAME' => $scriptName,
            'REQUEST_URI' => $scriptName . '/feature-active/?feature=FEATURE_NEXT_101',
        ]);

        /** @var \Slim\Container $container */
        $container = $app->getContainer();
        $container->offsetSet('request', Request::createFromEnvironment($env));
        $container->offsetSet('env.path', __DIR__ . '/_test-env/empty');

        $app->any('/feature-active/', function (ServerRequestInterface $request, ResponseInterface $response) {
            if (Feature::isActive($request->getQueryParams()['feature'])) {
                return $response->withStatus(200);
            }

            return $response->withStatus(400);
        })->setName('feature-active');
    }
}
