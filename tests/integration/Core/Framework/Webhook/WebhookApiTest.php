<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class WebhookApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testWriteWebhookViaApi(): void
    {
        $this->getBrowser()->request(
            'POST',
            '/api/webhook/',
            [
                'name' => 'My super webhook',
                'eventName' => 'product.written',
                'url' => 'http://localhost',
            ]
        );

        $response = $this->getBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode(), \print_r($response->getContent(), true));
    }
}
