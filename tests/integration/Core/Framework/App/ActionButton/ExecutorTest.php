<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\ActionButton;

use GuzzleHttp\Psr7\Response;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Executor;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\Payload\Source;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Tests\Integration\Core\Framework\App\AppSystemTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\GuzzleTestClientBehaviour;

/**
 * @internal
 */
#[CoversClass(Executor::class)]
#[Package('core')]
class ExecutorTest extends TestCase
{
    use AppSystemTestBehaviour;
    use GuzzleTestClientBehaviour;

    final public const SCHEMA_LOCATION = '/src/Core/Framework/App/ActionButton/appActionEndpointSchema.json';

    private Executor $executor;

    private string $schemaLocation;

    private AppEntity $app;

    protected function setUp(): void
    {
        $this->app = new AppEntity();
        $this->app->setAppSecret('s3cr3t');
        $this->executor = $this->getContainer()->get(Executor::class);
        $this->schemaLocation = $this->getContainer()->getParameter('kernel.project_dir') . self::SCHEMA_LOCATION;
    }

    public function testExecutorUsesCorrectSchema(): void
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $action = new AppAction(
            $this->app,
            new Source($appUrl, Random::getAlphanumericString(12), '1.0.0'),
            'https://test.com/my-action',
            'product',
            'detail',
            [Uuid::randomHex()],
            Uuid::randomHex()
        );

        $this->appendNewResponse(new Response(200));
        $this->executor->execute($action, Context::createDefaultContext());

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $result = $this->validateRequestSchema($body);

        $message = $this->parseSchemaErrors($result);

        static::assertTrue($result->isValid(), $message);
        static::assertNotNull($this->app->getAppSecret());

        static::assertSame(
            hash_hmac('sha256', $body, $this->app->getAppSecret()),
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
            $this->app,
            new Source($appUrl, Random::getAlphanumericString(12), '1.0.0'),
            'https://brokenServer.com',
            'product',
            'detail',
            [],
            Uuid::randomHex()
        );

        $this->appendNewResponse(new Response(500));

        static::expectException(AppException::class);
        $this->executor->execute($action, Context::createDefaultContext());
    }

    public function testTargetUrlIsCorrect(): void
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $targetUrl = 'https://my-server.com';
        $action = new AppAction(
            $this->app,
            new Source($appUrl, Random::getAlphanumericString(12), '1.0.0'),
            $targetUrl,
            'product',
            'detail',
            [],
            Uuid::randomHex()
        );

        $this->appendNewResponse(new Response(200));
        $this->executor->execute($action, Context::createDefaultContext());

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertSame($targetUrl, (string) $request->getUri());

        static::assertNotNull($this->app->getAppSecret());

        $body = $request->getBody()->getContents();
        static::assertSame(
            hash_hmac('sha256', $body, $this->app->getAppSecret()),
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
            $this->app,
            new Source($shopUrl, $shopId, $appVersion),
            $targetUrl,
            $entity,
            $actionName,
            $affectedIds,
            Uuid::randomHex()
        );

        $context = Context::createDefaultContext();

        $this->appendNewResponse(new Response(200));
        $this->executor->execute($action, $context);

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);
        $data = json_decode($body, true, 512, \JSON_THROW_ON_ERROR);

        $expectedSource = [
            'url' => $shopUrl,
            'appVersion' => $appVersion,
            'shopId' => $shopId,
            'inAppPurchases' => [],
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
        static::assertSame($context->getLanguageId(), $data['meta']['language']);

        static::assertNotNull($this->app->getAppSecret());

        static::assertSame(
            hash_hmac('sha256', $body, $this->app->getAppSecret()),
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
            $this->app,
            new Source($appUrl, Random::getAlphanumericString(12), '1.0.0'),
            'https://brokenServer.com',
            'product',
            'detail',
            [],
            Uuid::randomHex()
        );

        $this->appendNewResponse(new Response(200));

        $this->executor->execute($action, Context::createDefaultContext());

        $request = $this->getLastRequest();
        static::assertNotNull($request);

        static::assertSame('POST', $request->getMethod());
        $body = $request->getBody()->getContents();
        static::assertJson($body);

        $result = $this->validateRequestSchema($body);

        $message = $this->parseSchemaErrors($result);

        static::assertTrue($result->isValid(), $message);

        static::assertNotNull($this->app->getAppSecret());

        static::assertSame(
            hash_hmac('sha256', $body, $this->app->getAppSecret()),
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
            $this->app,
            new Source($appUrl, Random::getAlphanumericString(12), '1.0.0'),
            'https://brokenServer.com',
            'product',
            'detail',
            [],
            Uuid::randomHex()
        );

        $this->signResponse('123455');

        static::expectException(AppException::class);
        $this->executor->execute($action, Context::createDefaultContext());
    }

    public function testExecutorReturnMessageWithInvalidResponseFormat(): void
    {
        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $action = new AppAction(
            $this->app,
            new Source($appUrl, Random::getAlphanumericString(12), '1.0.0'),
            'https://brokenServer.com',
            'product',
            'detail',
            [],
            Uuid::randomHex()
        );

        $responseData = [
            'actionType' => '',
            'payload' => [],
        ];

        static::assertNotNull($this->app->getAppSecret());
        $this->signResponse($this->app->getAppSecret(), $responseData);

        static::expectException(AppException::class);
        $this->executor->execute($action, Context::createDefaultContext());
    }

    public function testThrowsExceptionIfAppUrlChangeIsDetected(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/../Manifest/_fixtures/test');
        $systemConfigService = $this->getContainer()->get(SystemConfigService::class);
        $systemConfigService->set(
            ShopIdProvider::SHOP_ID_SYSTEM_CONFIG_KEY,
            [
                'app_url' => 'http://random-shop.url',
                'value' => 'shopId',
            ]
        );

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $action = new AppAction(
            $this->app,
            new Source($appUrl, Random::getAlphanumericString(12), '1.0.0'),
            'https://test.com/my-action',
            'product',
            'detail',
            [Uuid::randomHex()],
            Uuid::randomHex()
        );

        static::assertNotNull($this->app->getAppSecret());
        $this->signResponse($this->app->getAppSecret());

        static::expectException(AppException::class);
        static::expectExceptionMessage('Detected APP_URL change');
        $this->executor->execute($action, Context::createDefaultContext());
    }

    private function parseSchemaErrors(ValidationResult $result): string
    {
        $error = $result->error();
        if (!$error instanceof ValidationError) {
            return '';
        }

        return print_r((new ErrorFormatter())->format($error), true);
    }

    private function validateRequestSchema(string $body): ValidationResult
    {
        $requestData = json_decode($body, null, 512, \JSON_THROW_ON_ERROR);
        $validator = new Validator();
        $resolver = $validator->resolver();
        static::assertNotNull($resolver);
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
