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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class IncrementApiControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use IntegrationTestBehaviour;

    private string $userId;

    protected function setUp(): void
    {
        /** @var Context $context */
        $context = $this->getBrowser()->getServerParameter(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT);

        /** @var AdminApiSource $source */
        $source = $context->getSource();
        static::assertNotNull($source->getUserId());
        $this->userId = Uuid::fromBytesToHex($source->getUserId());

        $this->getGateway($this->getBrowser())->reset($this->userId, 'foo');
    }

    public function testListEndpoint(): void
    {
        $client = $this->getBrowser();
        $gateway = $this->getGateway($client);

        $gateway->increment($this->userId, 'foo');
        $gateway->increment($this->userId, 'foo');
        $gateway->increment($this->userId, 'bar');

        $url = '/api/_action/increment/user_activity?cluster=' . $this->userId;
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

        $entries = $this->getGateway($client)->list($this->userId);

        static::assertArrayHasKey('foo', $entries);
        static::assertSame(1, $entries['foo']['count']);
    }

    public function testDecrementEndpoint(): void
    {
        $client = $this->getBrowser();
        $gateway = $this->getGateway($client);

        $gateway->increment($this->userId, 'foo');

        $entries = $gateway->list($this->userId);

        static::assertArrayHasKey('foo', $entries);
        static::assertSame(1, $entries['foo']['count']);

        $url = '/api/_action/decrement/user_activity';

        $client->request('POST', $url, [
            'key' => 'foo',
            'cluster' => $this->userId,
        ]);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        $entries = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($entries['success']);

        $entries = $this->getGateway($client)->list($this->userId);

        static::assertArrayHasKey('foo', $entries);
        static::assertSame(0, $entries['foo']['count']);
    }

    public function testResetEndpoint(): void
    {
        $client = $this->getBrowser();
        $gateway = $this->getGateway($client);

        $gateway->increment($this->userId, 'foo');
        $gateway->increment($this->userId, 'foo');
        $gateway->increment($this->userId, 'bar');

        $entries = $gateway->list($this->userId);

        static::assertArrayHasKey('foo', $entries);
        static::assertArrayHasKey('bar', $entries);
        static::assertSame(2, $entries['foo']['count']);
        static::assertSame(1, $entries['bar']['count']);

        $url = '/api/_action/reset-increment/user_activity';

        $client->request('POST', $url, [
            'cluster' => $this->userId,
        ]);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $entries = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($entries['success']);

        $entries = $this->getGateway($client)->list($this->userId);

        static::assertArrayHasKey('foo', $entries);
        static::assertArrayHasKey('bar', $entries);
        static::assertSame(0, $entries['foo']['count']);
        static::assertSame(0, $entries['bar']['count']);
    }

    public function testIncrementEndpointWithCustomCluster(): void
    {
        $clusterName = 'customer-cluster';
        $client = $this->getBrowser();
        $gateway = $this->getGateway($client);

        $gateway->reset($clusterName, 'foo');

        $url = '/api/_action/increment/user_activity';

        $client->request('POST', $url, [
            'key' => 'foo',
            'cluster' => $clusterName,
        ]);

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $entries = json_decode((string) $client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($entries['success']);

        $entries = $this->getGateway($client)->list($clusterName);

        static::assertArrayHasKey('foo', $entries);
        static::assertSame(1, $entries['foo']['count']);

        $url = '/api/_action/increment/user_activity?cluster=' . $clusterName;

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
        $client = $this->getBrowser();
        $gateway = $this->getGateway($client);

        $gateway->reset($this->userId);

        $gateway->increment($this->userId, 'foo');
        $gateway->increment($this->userId, 'baz');
        $gateway->increment($this->userId, 'bar');

        $entries = $gateway->list($this->userId);

        static::assertCount(3, $entries);

        $url = '/api/_action/delete-increment/user_activity';

        $client->request('DELETE', $url, [
            'cluster' => $this->userId,
            'keys' => ['foo', 'bar'],
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $entries = $this->getGateway($client)->list($this->userId);

        static::assertCount(1, $entries);

        static::assertArrayHasKey('baz', $entries);
        static::assertArrayNotHasKey('foo', $entries);
        static::assertArrayNotHasKey('bar', $entries);
    }

    public function testDeleteEndpointWithOnlyCluster(): void
    {
        $client = $this->getBrowser();
        $gateway = $this->getGateway($client);

        $gateway->reset($this->userId);

        $gateway->increment($this->userId, 'foo');
        $gateway->increment($this->userId, 'baz');
        $gateway->increment($this->userId, 'bar');

        $entries = $gateway->list($this->userId);

        static::assertCount(3, $entries);

        $gateway->reset($this->userId, 'foo');

        $url = '/api/_action/delete-increment/user_activity';

        $client->request('DELETE', $url, [
            'cluster' => $this->userId,
        ]);

        static::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $entries = $this->getGateway($client)->list($this->userId);

        static::assertEmpty($entries);
    }

    private function getGateway(KernelBrowser $browser): AbstractIncrementer
    {
        $gatewayRegistry = $browser->getContainer()->get('shopware.increment.gateway.registry');

        return $gatewayRegistry->get(IncrementGatewayRegistry::USER_ACTIVITY_POOL);
    }
}
