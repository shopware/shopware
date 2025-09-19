<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\Fingerprint\InstallationPath;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\FingerprintMatch;
use Shopware\Core\Framework\App\ShopId\FingerprintMismatch;
use Shopware\Core\Framework\App\ShopId\ShopId;
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
        $result = new FingerprintComparisonResult(
            [
                InstallationPath::IDENTIFIER => new FingerprintMatch(
                    InstallationPath::IDENTIFIER,
                    '/old/path',
                    100
                ),
            ],
            [
                AppUrl::IDENTIFIER => new FingerprintMismatch(
                    AppUrl::IDENTIFIER,
                    'https://old.url',
                    'https://new.url',
                    100
                ),
            ],
            75,
        );

        $e = new ShopIdChangeSuggestedException($shopId = ShopId::v2('123456789'), $result);

        static::assertSame(500, $e->getStatusCode());
        static::assertSame('FRAMEWORK__APP_SHOP_ID_CHANGE_SUGGESTED', $e->getErrorCode());
        static::assertSame('Changes in your system were detected that suggest a change of the shop ID.', $e->getMessage());
        static::assertSame($shopId, $e->shopId);
        static::assertSame($result, $e->comparisonResult);
    }
}
