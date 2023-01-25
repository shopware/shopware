<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\MessageQueue\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[Package('system-settings')]
class MessageQueueEndpointTest extends TestCase
{
    use IntegrationTestBehaviour;
    use AdminFunctionalTestBehaviour;

    public function testEndpoint(): void
    {
        $gatewayRegistry = $this->getContainer()->get('shopware.increment.gateway.registry');

        $gateway = $gatewayRegistry->get(IncrementGatewayRegistry::MESSAGE_QUEUE_POOL);

        $gateway->reset('message_queue_stats', 'foo');
        $gateway->reset('message_queue_stats', 'bar');
        $gateway->increment('message_queue_stats', 'foo');
        $gateway->increment('message_queue_stats', 'bar');
        $gateway->increment('message_queue_stats', 'bar');

        $url = '/api/_info/queue.json';
        $client = $this->getBrowser();
        $client->request('GET', $url);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        $entries = json_decode($client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

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
