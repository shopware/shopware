<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\Generator;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\Controller\InfoController;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannel\StoreApiInfoController;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group slow
 * @group skip-paratest
 */
class OpenApi3Test extends TestCase
{
    use KernelTestBehaviour;

    private static string $env = 'test';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        static::$env = $_SERVER['APP_ENV'] ?? 'test';
        $_SERVER['APP_ENV'] = 'prod';
        KernelLifecycleManager::ensureKernelShutdown();
        KernelLifecycleManager::bootKernel();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        $_SERVER['APP_ENV'] = static::$env;
        KernelLifecycleManager::ensureKernelShutdown();
    }

    public function testRequestOpenApi3Json(): void
    {
        $infoController = KernelLifecycleManager::getKernel()->getContainer()->get(StoreApiInfoController::class);

        $response = $infoController->info(new Request());

        static::assertSame(200, $response->getStatusCode(), print_r($response->getContent(), true));
    }

    public function testValidateStoreApiSchema(): void
    {
        $infoController = KernelLifecycleManager::getKernel()->getContainer()->get(StoreApiInfoController::class);

        $response = $infoController->info(new Request());

        $client = new Client();

        try {
            $response = $client->post('http://swagger:8080/validator/debug', [
                'json' => json_decode($response->getContent(), true),
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (ClientException | ConnectException $e) {
            static::markTestSkipped('Cannot reach validator swagger service: ' . $e->getMessage());
        }

        $content = json_decode((string) $response->getBody(), true);

        static::assertEmpty($content, json_encode($content, \JSON_PRETTY_PRINT));
    }

    public function testValidateAdminApiSchema(): void
    {
        $infoController = KernelLifecycleManager::getKernel()->getContainer()->get(InfoController::class);

        $response = $infoController->info(new Request());

        $client = new Client();

        try {
            $response = $client->post('https://swagger:8080/validator/debug', [
                'json' => json_decode($response->getContent(), true),
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (ClientException | ConnectException $e) {
            static::markTestSkipped('Cannot reach validator swagger service: ' . $e->getMessage());
        }

        $content = json_decode((string) $response->getBody(), true);

        static::assertEmpty($content, json_encode($content, \JSON_PRETTY_PRINT));
    }

    public function testValidateAdminApiSchemaJson(): void
    {
        $infoController = KernelLifecycleManager::getKernel()->getContainer()->get(InfoController::class);

        $response = $infoController->info(new Request(['type' => DefinitionService::TypeJson]));

        $client = new Client();

        try {
            $response = $client->post('https://swagger:8080/validator/debug', [
                'json' => json_decode($response->getContent(), true),
                'headers' => [
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (ClientException | ConnectException $e) {
            static::markTestSkipped('Cannot reach validator swagger service: ' . $e->getMessage());
        }

        $content = json_decode((string) $response->getBody(), true);

        static::assertEmpty($content, json_encode($content, \JSON_PRETTY_PRINT));
    }
}
