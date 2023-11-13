<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Asset;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class AssetPackageServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testRegisteredHavePrefix(): void
    {
        $assetService = $this->getContainer()->get('assets.packages');

        static::assertSame('/bundles/framework/test.txt', parse_url((string) $assetService->getPackage('@Framework')->getUrl('test.txt'), \PHP_URL_PATH));
        static::assertSame('/bundles/framework/test.txt', parse_url((string) $assetService->getUrl('/bundles/framework/test.txt'), \PHP_URL_PATH));
        static::assertSame('/test.txt', parse_url((string) $assetService->getUrl('test.txt'), \PHP_URL_PATH));
    }
}
