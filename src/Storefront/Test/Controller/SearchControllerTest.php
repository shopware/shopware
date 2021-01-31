<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class SearchControllerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider getProviderInvalidTerms
     */
    public function testSearchWithHtml(string $term): void
    {
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $_SERVER['APP_URL'] . '/search?search=' . urlencode($term));

        $html = $browser->getResponse()->getContent();

        static::assertStringNotContainsString($term, $html);
        static::assertStringContainsString(htmlentities($term), $html);
    }

    public function testProductCountSelection(): void
    {
        /** @var SystemConfigService $configService */
        $configService = $this->getContainer()->get(SystemConfigService::class);
        $configService->set('core.listing.productCountSteps', '12,24,36,48');
        $this->createProductOnDatabase();
        $browser = KernelLifecycleManager::createBrowser($this->getKernel());
        $browser->request('GET', $_SERVER['APP_URL'] . '/search?search=count');
        $html = $browser->getResponse()->getContent();

        static::assertRegExp('#<select id="limit".*<option\s*value="12".*>\s*12\s*</option>.*</select>#sx', $html);
        static::assertRegExp('#<select id="limit".*<option\s*value="24".*>\s*24\s*</option>.*</select>#sx', $html);
        static::assertRegExp('#<select id="limit".*<option\s*value="36".*>\s*36\s*</option>.*</select>#sx', $html);
        static::assertRegExp('#<select id="limit".*<option\s*value="48".*>\s*48\s*</option>.*</select>#sx', $html);
    }

    public function getProviderInvalidTerms(): iterable
    {
        yield ['<h1 style="color:red">Test</h1>'];
        yield ['<script\x20type="text/javascript">javascript:alert(1);</script>'];
        yield ['<img src=1 href=1 onerror="javascript:alert(1)"></img>'];
        yield ['<audio src=1 href=1 onerror="javascript:alert(1)"></audio>'];
        yield ['<video src=1 href=1 onerror="javascript:alert(1)"></video>'];
        yield ['<body src=1 href=1 onerror="javascript:alert(1)"></body>'];
        yield ['<object src=1 href=1 onerror="javascript:alert(1)"></object>'];
        yield ['<script src=1 href=1 onerror="javascript:alert(1)"></script>'];
        yield ['<svg onResize svg onResize="javascript:javascript:alert(1)"></svg onResize>'];
        yield ['"/><img/onerror=\x0Ajavascript:alert(1)\x0Asrc=xxx:x />'];
    }

    private function createProductOnDatabase(): void
    {
        $taxId = Uuid::randomHex();
        $context = Context::createDefaultContext();
;
        $this->getContainer()->get('product.repository')
            ->create(\array_map(static function (int $counter) use ($taxId): array {
                $productId = Uuid::randomHex();

                return [
                    'id' => $productId,
                    'name' => 'Test count product ' . $counter,
                    'productNumber' => $productId,
                    'stock' => 1,
                    'price' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'gross' => 15.99,
                            'net' => 10,
                            'linked' => false,
                        ],
                    ],
                    'tax' => [
                        'id' => $taxId,
                        'name' => 'testTaxRate',
                        'taxRate' => 15,
                    ],
                    'categories' => [
                        [
                            'id' => $productId,
                            'name' => 'Test category'
                        ],
                    ],
                    'visibilities' => [
                        [
                            'id' => $productId,
                            'salesChannelId' => Defaults::SALES_CHANNEL,
                            'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                        ],
                    ],
                ];
            }, range(0, 100)), $context);
    }
}
