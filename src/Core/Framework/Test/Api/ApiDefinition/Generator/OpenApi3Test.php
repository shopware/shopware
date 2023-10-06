<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\ApiDefinition\Generator;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\Controller\InfoController;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannel\StoreApiInfoController;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
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
        self::$env = $_SERVER['APP_ENV'] ?? 'test';
        $_SERVER['APP_ENV'] = 'prod';

        KernelLifecycleManager::ensureKernelShutdown();
        KernelLifecycleManager::bootKernel();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        $_SERVER['APP_ENV'] = self::$env;
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
        $schema = $response->getContent();
        static::assertIsString($schema);

        $this->assertValidSchema($schema);
    }

    public function testValidateAdminApiSchema(): void
    {
        $infoController = KernelLifecycleManager::getKernel()->getContainer()->get(InfoController::class);

        $response = $infoController->info(new Request());
        $schema = $response->getContent();
        static::assertIsString($schema);

        $this->assertValidSchema($schema);
    }

    public function testValidateAdminApiSchemaJson(): void
    {
        $infoController = KernelLifecycleManager::getKernel()->getContainer()->get(InfoController::class);

        $response = $infoController->info(new Request(['type' => DefinitionService::TYPE_JSON]));
        $schema = $response->getContent();
        static::assertIsString($schema);

        $this->assertValidSchema($schema);
    }

    private function assertValidSchema(string $schema): void
    {
        $client = new Client();
        $validatorURL = EnvironmentHelper::getVariable('SWAGGER_VALIDATOR_URL', 'https://validator.swagger.io/validator/debug');
        static::assertIsString($validatorURL);

        $response = $client->post($validatorURL, [
            'json' => json_decode($schema, true, 512, \JSON_THROW_ON_ERROR),
            'headers' => [
                'Accept' => 'application/json',
            ],
        ]);
        $content = json_decode((string) $response->getBody(), true, flags: \JSON_THROW_ON_ERROR);

        // The CI validator returns an empty response if the schema is valid
        // The public Web validator returns an object with an empty schemaValidationMessages array
        $messages = $content['schemaValidationMessages'] ?? [];

        static::assertEmpty($messages, (string) json_encode($content, \JSON_PRETTY_PRINT));
    }
}
