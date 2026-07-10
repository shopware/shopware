<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\TestUser;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class ApiControllerUpdateTest extends TestCase
{
    use AdminApiTestBehaviour;
    use BasicTestDataBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    public function testResponseDataTypeOnWrite(): void
    {
        $id = Uuid::randomHex();

        $data = ['id' => $id, 'name' => $id, 'taxRate' => 50];

        // create without response
        $this->getBrowser()->jsonRequest('POST', '/api/tax', $data);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/tax/' . $id, $response->headers->get('Location'));

        // update without response
        $this->getBrowser()->jsonRequest('PATCH', '/api/tax/' . $id, ['name' => 'foo']);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertSame('http://localhost/api/tax/' . $id, $response->headers->get('Location'));

        // with response
        $this->getBrowser()->jsonRequest('PATCH', '/api/tax/' . $id . '?_response=1', ['name' => 'foo']);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode());
        static::assertNull($response->headers->get('Location'));
    }

    public function testUpdateWithoutPermission(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'test tax',
            'taxRate' => 15,
        ];
        $browser = $this->getBrowser();
        TestUser::createNewTestUser(
            $browser->getContainer()->get(Connection::class),
            ['tax:read', 'tax:create']
        )->authorizeBrowser($browser);

        $browser->jsonRequest('POST', '/api/tax', $data);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());

        $browser->jsonRequest('PATCH', '/api/tax/' . $id, ['name' => 'foo']);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), (string) $response->getContent());

        $browser->jsonRequest('GET', '/api/tax/' . $id);
        $response = $browser->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $tax = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('data', $tax);
        static::assertSame('test tax', $tax['data']['attributes']['name']);
    }

    public function testAllowSettingNullToTranslatableFields(): void
    {
        $id = Uuid::randomHex();

        $entityName = 'product-feature-set';

        $client = $this->getBrowser();

        $client->jsonRequest('POST', '/api/' . $entityName, [
            'id' => $id,
            'features' => ['test' => true],
            'name' => 'test',
            'description' => 'test',
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->setServerParameter('HTTP_sw-language-id', $this->getDeDeLanguageId());

        $client->jsonRequest('PATCH', '/api/' . $entityName . '/' . $id, [
            'id' => $id,
            'name' => null,
            'description' => 'test',
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    public function testInvalidWriteInputExceptionIsConvertedToBadRequestOnUpdate(): void
    {
        $id = Uuid::randomHex();

        $entityName = 'product-feature-set';

        $client = $this->getBrowser();

        $client->jsonRequest('POST', '/api/' . $entityName, [
            'id' => $id,
            'features' => ['test' => true],
            'name' => 'test',
            'description' => 'test',
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->jsonRequest('PATCH', '/api/' . $entityName . '/' . $id, [2 => 'test']);

        $response = $client->getResponse();

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        static::assertSame(Response::HTTP_BAD_REQUEST, (int) $content['errors'][0]['status']);
        static::assertSame('Invalid payload. Should be associative array', $content['errors'][0]['detail']);
    }
}
