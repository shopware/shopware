<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\ActionButton;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\ValidationResult;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActionButton\AppAction;
use Shopware\Core\Framework\App\ActionButton\Executor;
use Shopware\Core\Framework\App\Exception\ActionProcessException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\App\GuzzleTestClientBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

class ExecutorTest extends TestCase
{
    use GuzzleTestClientBehaviour;

    public const SCHEMA_LOCATION = __DIR__ . '/../../../App/ActionButton/appActionEndpointSchema.json';

    /**
     * @var Executor
     */
    private $executor;

    public function setUp(): void
    {
        $this->executor = $this->getContainer()->get(Executor::class);
    }

    public function testExecutorUsesCorrectSchema(): void
    {
        $action = new AppAction(
            'https://test.com/my-action',
            getenv('APP_URL'),
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

        static::assertEquals(
            hash_hmac('sha256', $body, $action->getAppSecret()),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
    }

    public function testExecutorReturnMessageWithFailedRequests(): void
    {
        $action = new AppAction(
            'https://brokenServer.com',
            getenv('APP_URL'),
            '1.0.0',
            'product',
            'detail',
            [],
            's3cr3t',
            Random::getAlphanumericString(12),
            Uuid::randomHex()
        );

        $this->appendNewResponse(new Response(500));

        $this->expectException(ActionProcessException::class);
        $this->executor->execute($action, Context::createDefaultContext());
    }

    public function testTargetUrlIsCorrect(): void
    {
        $targetUrl = 'https://my-server.com';
        $action = new AppAction(
            $targetUrl,
            getenv('APP_URL'),
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

        $body = $request->getBody()->getContents();
        static::assertEquals(
            hash_hmac('sha256', $body, $action->getAppSecret()),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
    }

    public function testContentIsCorrect(): void
    {
        $targetUrl = 'https://test.com/my-action';
        $shopUrl = getenv('APP_URL');
        $appVersion = '1.0.0';
        $entity = 'product';
        $actionName = 'detail';
        $affectedIds = [Uuid::randomHex(), Uuid::randomHex()];
        $shopId = Random::getAlphanumericString(12);

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
        $data = json_decode($body, true);

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

        static::assertEquals(
            hash_hmac('sha256', $body, $action->getAppSecret()),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
    }

    public function testExecutorReturnEmptyResponseBody(): void
    {
        $action = new AppAction(
            'https://brokenServer.com',
            getenv('APP_URL'),
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

        static::assertEquals(
            hash_hmac('sha256', $body, $action->getAppSecret()),
            $request->getHeaderLine('shopware-shop-signature')
        );

        static::assertNotEmpty($request->getHeaderLine('sw-version'));
    }

    public function testExecutorReturnMessageWithWrongHMac(): void
    {
        $action = new AppAction(
            'https://brokenServer.com',
            getenv('APP_URL'),
            '1.0.0',
            'product',
            'detail',
            [],
            's3cr3t',
            Random::getAlphanumericString(12),
            Uuid::randomHex()
        );

        $this->signResponse('123455');

        $this->expectException(ActionProcessException::class);
        $this->executor->execute($action, Context::createDefaultContext());
    }

    public function testExecutorReturnMessageWithInvalidResponseFormat(): void
    {
        $appSecret = 's3cr3t';
        $action = new AppAction(
            'https://brokenServer.com',
            getenv('APP_URL'),
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

        $this->expectException(ActionProcessException::class);
        $this->executor->execute($action, Context::createDefaultContext());
    }

    private function parseSchemaErrors(ValidationResult $result): string
    {
        $message = '';

        foreach ($result->getErrors() as $validationError) {
            $message .= sprintf("Validation error at '%s' : %s \n", implode(',', $validationError->dataPointer()), $validationError->keyword());
            $message .= json_encode($validationError->keywordArgs(), \JSON_PRETTY_PRINT);
        }

        return $message;
    }

    private function validateRequestSchema(string $body): ValidationResult
    {
        $requestData = json_decode($body);
        $schema = Schema::fromJsonString(file_get_contents(self::SCHEMA_LOCATION));
        $validator = new Validator();

        return $validator->schemaValidation($requestData, $schema);
    }

    private function signResponse(string $appSecret, ?array $responseData = null): void
    {
        $responseData = $responseData ?: [
            'actionType' => 'openNewTab',
            'payload' => [
                'redirectUrl' => 'https://www.google.com',
            ],
        ];

        $responseData = (string) json_encode($responseData);

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
