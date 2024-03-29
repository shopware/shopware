<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Webhook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Webhook\Webhook;

/**
 * @internal
 */
#[CoversClass(Webhook::class)]
class WebhookTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml');

        static::assertNotNull($manifest->getWebhooks());
        static::assertCount(3, $manifest->getWebhooks()->getWebhooks());

        $firstWebhook = $manifest->getWebhooks()->getWebhooks()[0];
        static::assertEquals('hook1', $firstWebhook->getName());
        static::assertEquals('https://test.com/hook', $firstWebhook->getUrl());
        static::assertEquals('checkout.customer.before.login', $firstWebhook->getEvent());
        static::assertFalse($firstWebhook->getOnlyLiveVersion());

        $secondWebhook = $manifest->getWebhooks()->getWebhooks()[1];
        static::assertEquals('hook2', $secondWebhook->getName());
        static::assertEquals('https://test.com/hook2', $secondWebhook->getUrl());
        static::assertEquals('product.written', $secondWebhook->getEvent());
        static::assertTrue($secondWebhook->getOnlyLiveVersion());

        $thirdWebhook = $manifest->getWebhooks()->getWebhooks()[2];
        static::assertEquals('hook3', $thirdWebhook->getName());
        static::assertEquals('https://test.com/hook3', $thirdWebhook->getUrl());
        static::assertEquals('product.written', $thirdWebhook->getEvent());
        static::assertFalse($thirdWebhook->getOnlyLiveVersion());
    }
}
