<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;

class UserControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testMe(): void
    {
        $url = sprintf('/api/v%s/_info/me', PlatformRequest::API_VERSION);
        $client = $this->getBrowser();
        $client->request('GET', $url);

        static::assertSame(200, $client->getResponse()->getStatusCode());

        $content = json_decode($client->getResponse()->getContent(), true);

        static::assertArrayHasKey('attributes', $content['data']);
        static::assertSame('user', $content['data']['type']);
        static::assertSame('admin@example.com', $content['data']['attributes']['email']);
        static::assertNotNull($content['data']['relationships']['avatarMedia']);
    }
}
