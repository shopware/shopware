<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;

class ConsumeMessagesControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testConsumeMessages()
    {
        $start = microtime(true);
        $url = sprintf('/api/v%s/_action/message-queue/consume', PlatformRequest::API_VERSION);
        $client = $this->getClient();
        $client->request('POST', $url, ['receiver' => 'default']);
        $duration = (int) (microtime(true) - $start);

        static::assertSame(2, $duration);
        static::assertSame(200, $client->getResponse()->getStatusCode());
        static::assertSame(json_encode(['handledMessages' => 0]), $client->getResponse()->getContent());
    }
}
