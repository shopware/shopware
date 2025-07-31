<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\Fingerprint\InstallationPath;
use Shopware\Core\Framework\App\ShopId\Fingerprint\SalesChannelDomainUrls;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(ShopIdChangeSuggestedException::class)]
#[Package('framework')]
class ShopIdChangeSuggestedExceptionTest extends TestCase
{
    public function testException(): void
    {
        $mismatchingFingerprints = [
            AppUrl::IDENTIFIER,
            InstallationPath::IDENTIFIER,
            SalesChannelDomainUrls::IDENTIFIER,
        ];

        $e = new ShopIdChangeSuggestedException($mismatchingFingerprints);

        static::assertSame(500, $e->getStatusCode());
        static::assertSame('FRAMEWORK__APP_SHOP_ID_CHANGE_SUGGESTED', $e->getErrorCode());
        static::assertSame('Changes in your system were detected that suggest a change of the shop ID.', $e->getMessage());
        static::assertSame($mismatchingFingerprints, $e->mismatchingFingerprints);
    }
}
