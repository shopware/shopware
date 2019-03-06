<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\PlatformRequest;

class ConsumeMessagesControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour, QueueTestBehaviour;

    public function testConsumeMessages()
    {
        $url = sprintf('/api/v%s/_action/message-queue/consume', PlatformRequest::API_VERSION);
        $client = $this->getClient();
        $client->request('POST', $url, ['receiver' => 'default']);

        static::assertSame(200, $client->getResponse()->getStatusCode());
        $response = json_decode($client->getResponse()->getContent(), true);
        static::assertArrayHasKey('handledMessages', $response);
        static::assertIsInt($response['handledMessages']);
    }
}
