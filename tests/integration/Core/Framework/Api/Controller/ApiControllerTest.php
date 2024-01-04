<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ApiController::class)]
class ApiControllerTest extends TestCase
{
    use AdminApiTestBehaviour;
    use IntegrationTestBehaviour;

    public function testAllowSettingNullToTranslatableFields(): void
    {
        $id = Uuid::randomHex();

        $entityName = 'product-feature-set';

        $client = $this->getBrowser();

        $client->request('POST', '/api/' . $entityName, [
            'id' => $id,
            'features' => ['test' => true],
            'name' => 'test',
            'description' => 'test',
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->setServerParameter('HTTP_sw-language-id', $this->getDeDeLanguageId());

        $client->request('PATCH', '/api/' . $entityName . '/' . $id, [
            'id' => $id,
            'name' => null,
            'description' => 'test',
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    public function testInvalidWriteInputExceptionIsConvertedToBadRequestOnCreate(): void
    {
        $entityName = 'product-feature-set';

        $client = $this->getBrowser();

        $client->request('POST', '/api/' . $entityName, [2 => 'test']);

        /** @var string $response */
        $response = $client->getResponse()->getContent();

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        static::assertEquals(Response::HTTP_BAD_REQUEST, $response['errors'][0]['status']);
        static::assertEquals('Invalid payload. Should be associative array', $response['errors'][0]['detail']);
    }

    public function testInvalidWriteInputExceptionIsConvertedToBadRequestOnUpdate(): void
    {
        $id = Uuid::randomHex();

        $entityName = 'product-feature-set';

        $client = $this->getBrowser();

        $client->request('POST', '/api/' . $entityName, [
            'id' => $id,
            'features' => ['test' => true],
            'name' => 'test',
            'description' => 'test',
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->request('PATCH', '/api/' . $entityName . '/' . $id, [2 => 'test']);

        /** @var string $response */
        $response = $client->getResponse()->getContent();

        $response = json_decode($response, true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
        static::assertEquals(Response::HTTP_BAD_REQUEST, $response['errors'][0]['status']);
        static::assertEquals('Invalid payload. Should be associative array', $response['errors'][0]['detail']);
    }
}
