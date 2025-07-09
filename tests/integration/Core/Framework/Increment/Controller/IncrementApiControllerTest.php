<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Increment\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Increment\AbstractIncrementer;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class IncrementApiControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use IntegrationTestBehaviour;

    private AbstractIncrementer $gateway;

    private string $userId;

    protected function setUp(): void
    {
        $gatewayRegistry = static::getContainer()->get('shopware.increment.gateway.registry');

        $gateway = $gatewayRegistry->get(IncrementGatewayRegistry::USER_ACTIVITY_POOL);

        $this->gateway = $gateway;

        /** @var Context $context */
        $context = $this->getBrowser()->getServerParameter(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        /** @var AdminApiSource $source */
        $source = $context->getSource();
        static::assertNotNull($source->getUserId());
        $this->userId = Uuid::fromBytesToHex($source->getUserId());

        $this->gateway->reset($this->userId, 'foo');
    }

    public function testListEndpoint(): void
    {
        $this->gateway->increment($this->userId, 'foo');
        $this->gateway->increment($this->userId, 'foo');
        $this->gateway->increment($this->userId, 'bar');

        $url = '/api/_action/increment/user_activity?cluster=' . $this->userId;
        $client = $this->getBrowser();
        $client->request('GET', $url);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        $entries = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($entries);
        static::assertArrayHasKey('foo', $entries);
        static::assertSame(2, $entries['foo']['count']);
        static::assertArrayHasKey('bar', $entries);
        static::assertSame(1, $entries['bar']['count']);
    }

    public function testEndpointWithoutCluster(): void
    {
        $url = '/api/_action/increment/user_activity';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $errors = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['errors'];

        static::assertSame('Parameter "cluster" is missing.', $errors[0]['detail']);
    }

    public function testIncrementEndpointWithInvalidPool(): void
    {
        $url = '/api/_action/increment/unknown-pool?cluster=' . $this->userId;

        $client = $this->getBrowser();
        $client->request('POST', $url, [
            'key' => 'foo',
            'cluster' => $this->userId,
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $errors = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['errors'];

        static::assertSame('Increment gateway for pool "unknown-pool" was not found.', $errors[0]['detail']);
    }

    public function testIncrementEndpoint(): void
    {
        $url = '/api/_action/increment/user_activity';

        $client = $this->getBrowser();
        $client->request('POST', $url, [
            'key' => 'foo',
            'cluster' => $this->userId,
        ]);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $entries = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($entries['success']);

        $entries = $this->gateway->list($this->userId);

        static::assertArrayHasKey('foo', $entries);
        static::assertSame(1, $entries['foo']['count']);
    }

    public function testDecrementEndpoint(): void
    {
        $this->gateway->increment($this->userId, 'foo');

        $entries = $this->gateway->list($this->userId);

        static::assertArrayHasKey('foo', $entries);
        static::assertSame(1, $entries['foo']['count']);

        $url = '/api/_action/decrement/user_activity';

        $client = $this->getBrowser();
        $client->request('POST', $url, [
            'key' => 'foo',
            'cluster' => $this->userId,
        ]);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        $entries = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($entries['success']);

        $entries = $this->gateway->list($this->userId);

        static::assertArrayHasKey('foo', $entries);
        static::assertSame(0, $entries['foo']['count']);
    }

    public function testResetEndpoint(): void
    {
        $this->gateway->increment($this->userId, 'foo');
        $this->gateway->increment($this->userId, 'foo');
        $this->gateway->increment($this->userId, 'bar');

        $entries = $this->gateway->list($this->userId);

        static::assertArrayHasKey('foo', $entries);
        static::assertArrayHasKey('bar', $entries);
        static::assertSame(2, $entries['foo']['count']);
        static::assertSame(1, $entries['bar']['count']);

        $url = '/api/_action/reset-increment/user_activity';

        $client = $this->getBrowser();
        $client->request('POST', $url, [
            'cluster' => $this->userId,
        ]);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $entries = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($entries['success']);

        $entries = $this->gateway->list($this->userId);

        static::assertArrayHasKey('foo', $entries);
        static::assertArrayHasKey('bar', $entries);
        static::assertSame(0, $entries['foo']['count']);
        static::assertSame(0, $entries['bar']['count']);
    }

    public function testIncrementEndpointWithCustomCluster(): void
    {
        $clusterName = 'customer-cluster';
        $this->gateway->reset($clusterName, 'foo');

        $url = '/api/_action/increment/user_activity';

        $client = $this->getBrowser();
        $client->request('POST', $url, [
            'key' => 'foo',
            'cluster' => $clusterName,
        ]);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $entries = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($entries['success']);

        $entries = $this->gateway->list($clusterName);

        static::assertArrayHasKey('foo', $entries);
        static::assertSame(1, $entries['foo']['count']);

        $url = '/api/_action/increment/user_activity?cluster=' . $clusterName;

        $client = $this->getBrowser();
        $client->request('GET', $url);

        $entries = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        static::assertArrayHasKey('foo', $entries);
        static::assertSame(1, $entries['foo']['count']);
    }

    public function testDeleteEndpointWithInvalidKeys(): void
    {
        $clusterName = 'customer-cluster';
        $url = '/api/_action/delete-increment/user_activity';

        $client = $this->getBrowser();
        $client->request('DELETE', $url, [
            'cluster' => $clusterName,
            'keys' => 'invalidFoo',
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $errors = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['errors'];

        static::assertSame('Parameter "keys" must be an array.', $errors[0]['detail']);
    }

    public function testDeleteEndpointWithKeys(): void
    {
        $this->gateway->reset($this->userId);

        $this->gateway->increment($this->userId, 'foo');
        $this->gateway->increment($this->userId, 'baz');
        $this->gateway->increment($this->userId, 'bar');

        $entries = $this->gateway->list($this->userId);

        static::assertCount(3, $entries);

        $url = '/api/_action/delete-increment/user_activity';

        $client = $this->getBrowser();
        $client->request('DELETE', $url, [
            'cluster' => $this->userId,
            'keys' => ['foo', 'bar'],
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $entries = $this->gateway->list($this->userId);

        static::assertCount(1, $entries);

        static::assertArrayHasKey('baz', $entries);
        static::assertArrayNotHasKey('foo', $entries);
        static::assertArrayNotHasKey('bar', $entries);
    }

    public function testDeleteEndpointWithOnlyCluster(): void
    {
        $this->gateway->reset($this->userId);

        $this->gateway->increment($this->userId, 'foo');
        $this->gateway->increment($this->userId, 'baz');
        $this->gateway->increment($this->userId, 'bar');

        $entries = $this->gateway->list($this->userId);

        static::assertCount(3, $entries);

        $this->gateway->reset($this->userId, 'foo');

        $url = '/api/_action/delete-increment/user_activity';

        $client = $this->getBrowser();
        $client->request('DELETE', $url, [
            'cluster' => $this->userId,
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $entries = $this->gateway->list($this->userId);

        static::assertEmpty($entries);
    }
}
