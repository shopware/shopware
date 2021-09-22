<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MessageQueueEndpointTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminFunctionalTestBehaviour;

    public function testEndpoint(): void
    {
        $gateway = $this->getContainer()->get('shopware.queue.monitoring.gateway');
        $gateway->reset('foo');
        $gateway->reset('bar');
        $gateway->increment('foo');
        $gateway->increment('bar');
        $gateway->increment('bar');

        $url = '/api/_info/queue.json';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        $entries = json_decode($client->getResponse()->getContent(), true);

        $mapped = [];
        foreach ($entries as $entry) {
            $mapped[$entry['name']] = $entry['size'];
        }

        static::assertArrayHasKey('foo', $mapped);
        static::assertEquals(1, $mapped['foo']);
        static::assertArrayHasKey('bar', $mapped);
        static::assertEquals(2, $mapped['bar']);
    }
}
