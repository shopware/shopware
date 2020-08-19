<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;

class WebhooksTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/test/manifest.xml');

        static::assertNotNull($manifest->getWebhooks());
        static::assertCount(2, $manifest->getWebhooks()->getWebhooks());

        $firstWebhook = $manifest->getWebhooks()->getWebhooks()[0];
        static::assertEquals('hook1', $firstWebhook->getName());
        static::assertEquals('https://test.com/hook', $firstWebhook->getUrl());
        static::assertEquals('checkout.customer.before.login', $firstWebhook->getEvent());

        $secondWebhook = $manifest->getWebhooks()->getWebhooks()[1];
        static::assertEquals('hook2', $secondWebhook->getName());
        static::assertEquals('https://test.com/hook2', $secondWebhook->getUrl());
        static::assertEquals('checkout.order.placed', $secondWebhook->getEvent());
    }
}
