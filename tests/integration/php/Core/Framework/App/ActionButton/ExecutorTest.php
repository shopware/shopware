<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\ActionButton;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Resolvers\SchemaResolver;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Executor;
use Shopware\Core\Framework\App\Exception\ActionProcessException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
class ExecutorTest extends TestCase
{
    use GuzzleTestClientBehaviour;
    use AppSystemTestBehaviour;

    final public const SCHEMA_LOCATION = '/src/Core/Framework/App/ActionButton/appActionEndpointSchema.json';

    private Executor $executor;

    private string $schemaLocation;

    public function setUp(): void
    {
        $this->executor = $this->getContainer()->get(Executor::class);
        $this->schemaLocation = $this->getContainer()->getParameter('kernel.project_dir') . self::SCHEMA_LOCATION;
    }

    public function testExecutorUsesCorrectSchema(): void
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $action = new AppAction(
            'https://test.com/my-action',
            $appUrl,
            '1.0.0',
            'product',
            'detail',
            [Uuid::randomHex()],
            's3cr3t',
            Random::getAlphanumericString(12),
            Uuid::randomHex()
        );

        $this->appendNewResponse(new Response(200));
        $this->executor->execute($action, Context::createDefaultContext());

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $result = $this->validateRequestSchema($body);

        $message = $this->parseSchemaErrors($result);

        static::assertTrue($result->isValid(), $message);

        $appSecret = $action->getAppSecret();
        static::assertNotNull($appSecret);

        static::assertEquals(
            hash_hmac('sha256', $body, $appSecret),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
    }

    public function testExecutorReturnMessageWithFailedRequests(): void
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $action = new AppAction(
            'https://brokenServer.com',
            $appUrl,
            '1.0.0',
            'product',
            'detail',
            [],
            's3cr3t',
            Random::getAlphanumericString(12),
            Uuid::randomHex()
        );

        $this->appendNewResponse(new Response(500));

        static::expectException(ActionProcessException::class);
        $this->executor->execute($action, Context::createDefaultContext());
    }

    public function testTargetUrlIsCorrect(): void
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $targetUrl = 'https://my-server.com';
        $action = new AppAction(
            $targetUrl,
            $appUrl,
            '1.0.0',
            'product',
            'detail',
            [],
            's3cr3t',
            Random::getAlphanumericString(12),
            Uuid::randomHex()
        );

        $this->appendNewResponse(new Response(200));
        $this->executor->execute($action, Context::createDefaultContext());

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertEquals($targetUrl, (string) $request->getUri());

        $appSecret = $action->getAppSecret();
        static::assertNotNull($appSecret);

        $body = $request->getBody()->getContents();
        static::assertEquals(
            hash_hmac('sha256', $body, $appSecret),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
    }

    public function testContentIsCorrect(): void
    {
        $targetUrl = 'https://test.com/my-action';
        $shopUrl = EnvironmentHelper::getVariable('APP_URL');
        $appVersion = '1.0.0';
        $entity = 'product';
        $actionName = 'detail';
        $affectedIds = [Uuid::randomHex(), Uuid::randomHex()];
        $shopId = Random::getAlphanumericString(12);

        static::assertIsString($shopUrl);

        $action = new AppAction(
            $targetUrl,
            $shopUrl,
            $appVersion,
            $entity,
            $actionName,
            $affectedIds,
            's3cr3t',
            $shopId,
            Uuid::randomHex()
        );

        $context = Context::createDefaultContext();

        $this->appendNewResponse(new Response(200));
        $this->executor->execute($action, $context);

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);
        $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);

        $expectedSource = [
            'url' => $shopUrl,
            'appVersion' => $appVersion,
            'shopId' => $shopId,
        ];
        $expectedData = [
            'ids' => $affectedIds,
            'action' => $actionName,
            'entity' => $entity,
        ];

        static::assertEquals($expectedSource, $data['source']);
        static::assertEquals($expectedData, $data['data']);
        static::assertNotEmpty($data['meta']['timestamp']);
        static::assertTrue(Uuid::isValid($data['meta']['reference']));
        static::assertEquals($context->getLanguageId(), $data['meta']['language']);

        $appSecret = $action->getAppSecret();
        static::assertNotNull($appSecret);

        static::assertEquals(
            hash_hmac('sha256', $body, $appSecret),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
    }

    public function testExecutorReturnEmptyResponseBody(): void
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $action = new AppAction(
            'https://brokenServer.com',
            $appUrl,
            '1.0.0',
            'product',
            'detail',
            [],
            's3cr3t',
            Random::getAlphanumericString(12),
            Uuid::randomHex()
        );

        $this->appendNewResponse(new Response(200));

        $this->executor->execute($action, Context::createDefaultContext());

        /** @var Request $request */
        $request = $this->getLastRequest();

        static::assertEquals('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $result = $this->validateRequestSchema($body);

        $message = $this->parseSchemaErrors($result);

        static::assertTrue($result->isValid(), $message);

        $appSecret = $action->getAppSecret();
        static::assertNotNull($appSecret);

        static::assertEquals(
            hash_hmac('sha256', $body, $appSecret),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_USER_LANGUAGE));
        static::assertNotEmpty($request->getHeaderLine(AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE));
    }

    public function testExecutorReturnMessageWithWrongHMac(): void
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $action = new AppAction(
            'https://brokenServer.com',
            $appUrl,
            '1.0.0',
            'product',
            'detail',
            [],
            's3cr3t',
            Random::getAlphanumericString(12),
            Uuid::randomHex()
        );

        $this->signResponse('123455');

        static::expectException(ActionProcessException::class);
        $this->executor->execute($action, Context::createDefaultContext());
    }

    public function testExecutorReturnMessageWithInvalidResponseFormat(): void
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $appSecret = 's3cr3t';
        $action = new AppAction(
            'https://brokenServer.com',
            $appUrl,
            '1.0.0',
            'product',
            'detail',
            [],
            $appSecret,
            Random::getAlphanumericString(12),
            Uuid::randomHex()
        );

        $responseData = [
            'actionType' => '',
            'payload' => [],
        ];

        $this->signResponse($appSecret, $responseData);

        static::expectException(ActionProcessException::class);
        $this->executor->execute($action, Context::createDefaultContext());
    }

    public function testThrowsExceptionIfAppUrlChangeIsDetected(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY, ['app_url' => 'http://random-shop.url']);

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $action = new AppAction(
            'https://test.com/my-action',
            $appUrl,
            '1.0.0',
            'product',
            'detail',
            [Uuid::randomHex()],
            's3cr3t',
            Random::getAlphanumericString(12),
            Uuid::randomHex()
        );

        $this->signResponse('123455');

        static::expectException(ActionProcessException::class);
        static::expectExceptionMessage('Detected APP_URL change');
        $this->executor->execute($action, Context::createDefaultContext());
    }

    private function parseSchemaErrors(ValidationResult $result): string
    {
        $error = $result->error();
        if (!$error) {
            return '';
        }

        return print_r((new ErrorFormatter())->format($error), true);
    }

    private function validateRequestSchema(string $body): ValidationResult
    {
        $requestData = json_decode($body, null, 512, \JSON_THROW_ON_ERROR);
        $validator = new Validator();
        /** @var SchemaResolver $resolver */
        $resolver = $validator->resolver();
        $resolver->registerFile(
            'http://api.example.com/appActionEndpointSchema.json',
            $this->schemaLocation
        );

        return $validator->validate($requestData, 'http://api.example.com/appActionEndpointSchema.json');
    }

    /**
     * @param array<string, mixed>|null $responseData
     */
    private function signResponse(string $appSecret, ?array $responseData = null): void
    {
        $responseData = $responseData ?: [
            'actionType' => 'openNewTab',
            'payload' => [
                'redirectUrl' => 'https://www.google.com',
            ],
        ];

        $responseData = (string) json_encode($responseData, \JSON_THROW_ON_ERROR);

        $this->appendNewResponse(
            new Response(
                200,
                [
                    'shopware-app-signature' => hash_hmac('sha256', $responseData, $appSecret),
                ],
                $responseData
            )
        );
    }
}
