<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest\Xml\CustomFieldTypes;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;

class PaymentMethodTest extends TestCase
{
    public function testFromXml(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../_fixtures/test/manifest.xml');

        static::assertNotNull($manifest->getPayments());
        static::assertCount(2, $manifest->getPayments()->getPaymentMethods());

        $firstWebhook = $manifest->getPayments()->getPaymentMethods()[0];
        static::assertNotNull($firstWebhook);
        static::assertSame('myMethod', $firstWebhook->getIdentifier());
        static::assertSame('https://payment.app/payment/process', $firstWebhook->getPayUrl());
        static::assertSame('https://payment.app/payment/finalize', $firstWebhook->getFinalizeUrl());
        static::assertSame('Resources/payment.png', $firstWebhook->getIcon());
        static::assertSame([
            'en-GB' => 'The app payment method',
            'de-DE' => 'Die App Zahlungsmethode',
        ], $firstWebhook->getName());
        static::assertSame([
            'en-GB' => 'This is a description',
            'de-DE' => 'Die Zahlungsmethoden-Beschreibung',
        ], $firstWebhook->getDescription());

        $secondWebhook = $manifest->getPayments()->getPaymentMethods()[1];
        static::assertNotNull($secondWebhook);
        static::assertSame('anotherMethod', $secondWebhook->getIdentifier());
        static::assertNull($secondWebhook->getPayUrl());
        static::assertNull($secondWebhook->getFinalizeUrl());
        static::assertNull($secondWebhook->getIcon());
        static::assertSame([
            'en-GB' => 'Another app payment method',
        ], $secondWebhook->getName());
        static::assertSame([
            'en-GB' => 'This is another description',
        ], $secondWebhook->getDescription());
    }
}
