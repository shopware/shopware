<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;

class InfoControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testGetConfig(): void
    {
        $expected = [
            'version' => Kernel::SHOPWARE_FALLBACK_VERSION,
            'versionRevision' => str_repeat('0', 32),
            'adminWorker' => [
                'enableAdminWorker' => $this->getContainer()->getParameter('shopware.admin_worker.enable_admin_worker'),
                'transports' => $this->getContainer()->getParameter('shopware.admin_worker.transports'),
            ],
            'bundles' => [
                'Storefront' => [
                    'css' => [
                        '/bundles/storefront/administration/css/storefront.css',
                    ],
                    'js' => [
                        '/bundles/storefront/administration/js/storefront.js',
                    ],
                ],
            ],
        ];

        $url = sprintf('/api/v%s/_info/config', PlatformRequest::API_VERSION);
        $client = $this->getBrowser();
        $client->request('GET', $url);

        static::assertSame(200, $client->getResponse()->getStatusCode());
        static::assertSame(json_encode($expected), $client->getResponse()->getContent(), print_r($client->getResponse()->getContent(), true));
    }
}
