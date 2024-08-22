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
        $gatewayRegistry = $this->getContainer()->get('shopware.increment.gateway.registry');

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

        static::assertArrayHasKey('foo', $entries);
        static::assertEquals(2, $entries['foo']['count']);
        static::assertArrayHasKey('bar', $entries);
        static::assertEquals(1, $entries['bar']['count']);
    }

    public function testEndpointWithoutCluster(): void
    {
        $url = '/api/_action/increment/user_activity';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        static::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());

        $errors = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR)['errors'];

        static::assertEquals('Parameter "cluster" is missing.', $errors[0]['detail']);
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

        static::assertEquals('Increment gateway for pool "unknown-pool" was not found.', $errors[0]['detail']);
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
        static::assertEquals(1, $entries['foo']['count']);
    }

    public function testDecrementEndpoint(): void
    {
        $this->gateway->increment($this->userId, 'foo');

        $entries = $this->gateway->list($this->userId);

        static::assertArrayHasKey('foo', $entries);
        static::assertEquals(1, $entries['foo']['count']);

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
        static::assertEquals(0, $entries['foo']['count']);
    }

    public function testResetEndpoint(): void
    {
        $this->gateway->increment($this->userId, 'foo');
        $this->gateway->increment($this->userId, 'foo');
        $this->gateway->increment($this->userId, 'bar');

        $entries = $this->gateway->list($this->userId);

        static::assertArrayHasKey('foo', $entries);
        static::assertArrayHasKey('bar', $entries);
        static::assertEquals(2, $entries['foo']['count']);
        static::assertEquals(1, $entries['bar']['count']);

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
        static::assertEquals(0, $entries['foo']['count']);
        static::assertEquals(0, $entries['bar']['count']);
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
        static::assertEquals(1, $entries['foo']['count']);

        $url = '/api/_action/increment/user_activity?cluster=' . $clusterName;

        $client = $this->getBrowser();
        $client->request('GET', $url);

        $entries = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        static::assertArrayHasKey('foo', $entries);
        static::assertEquals(1, $entries['foo']['count']);
    }
}
