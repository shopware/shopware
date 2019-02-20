<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;

class InfoControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testGetConfig(): void
    {
        $expected = [
            'adminWorker' => [
                'enableAdminWorker' => $this->getContainer()->getParameter('shopware.admin_worker.enable_admin_worker'),
                'pollInterval' => $this->getContainer()->getParameter('shopware.admin_worker.poll_interval'),
            ],
        ];

        $url = sprintf('/api/v%s/_info/config', PlatformRequest::API_VERSION);
        $client = $this->getClient();
        $client->request('GET', $url);

        static::assertSame(200, $client->getResponse()->getStatusCode());
        static::assertSame(json_encode($expected), $client->getResponse()->getContent());
    }
}
