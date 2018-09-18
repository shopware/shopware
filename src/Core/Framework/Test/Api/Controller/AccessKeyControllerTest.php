<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;

class AccessKeyControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testIntegrationAccessKey(): void
    {
        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/integration/actions/generate-key');

        $response = $this->getClient()->getResponse();
        $body = json_decode($response->getContent(), true);

        static::assertSame(200, $response->getStatusCode(), print_r($body, true));
        static::assertInternalType('array', $body);
        static::assertArrayHasKey('accessKey', $body);
        static::assertArrayHasKey('secretAccessKey', $body);
        static::assertNotEmpty($body['secretAccessKey']);
        static::assertNotEmpty($body['accessKey']);

        $e = null;
        try {
            AccessKeyHelper::getOrigin($body['accessKey']);
        } catch (\Exception $e) {
        }
        static::assertNull($e);
    }

    public function testUserAccessKey(): void
    {
        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/user/actions/generate-key');

        $response = $this->getClient()->getResponse();
        $body = json_decode($response->getContent(), true);

        static::assertSame(200, $response->getStatusCode(), print_r($body, true));
        static::assertInternalType('array', $body);
        static::assertArrayHasKey('accessKey', $body);
        static::assertArrayHasKey('secretAccessKey', $body);
        static::assertNotEmpty($body['secretAccessKey']);
        static::assertNotEmpty($body['accessKey']);

        $e = null;
        try {
            AccessKeyHelper::getOrigin($body['accessKey']);
        } catch (\Exception $e) {
        }
        static::assertNull($e);
    }

    public function testSalesChannelAccessKey(): void
    {
        $this->getClient()->request('GET', '/api/v' . PlatformRequest::API_VERSION . '/sales-channel/actions/generate-key');

        $response = $this->getClient()->getResponse();
        $body = json_decode($response->getContent(), true);

        static::assertSame(200, $response->getStatusCode(), print_r($body, true));
        static::assertInternalType('array', $body);
        static::assertArrayHasKey('accessKey', $body);
        static::assertNotEmpty($body['accessKey']);

        $e = null;
        try {
            AccessKeyHelper::getOrigin($body['accessKey']);
        } catch (\Exception $e) {
        }
        static::assertNull($e);
    }
}
