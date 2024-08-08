<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Gateways;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Gateway\InAppPurchasesGateway;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(InAppPurchasesGateway::class)]
#[Package('checkout')]
class InAppPurchasesGatewayTest extends TestCase
{
    public function testParse(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/_fixtures/testGateway/manifest.xml');

        static::assertNotNull($manifest->getGateways());

        $gateways = $manifest->getGateways();

        static::assertNotNull($gateways->getInAppPurchasesGateway());

        $inAppPurchasesFilter = $gateways->getInAppPurchasesGateway();
        static::assertSame('https://foo.bar/example/iap-filter', $inAppPurchasesFilter->getUrl());
    }
}
