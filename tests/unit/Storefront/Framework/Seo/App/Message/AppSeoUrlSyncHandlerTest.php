<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlSynchronizer;
use Shopware\Storefront\Framework\Seo\App\Message\AppSeoUrlSyncHandler;
use Shopware\Storefront\Framework\Seo\App\Message\AppSeoUrlSyncMessage;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlSyncHandler::class)]
class AppSeoUrlSyncHandlerTest extends TestCase
{
    private const APP_ID = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    #[DataProvider('messages')]
    public function testTheStaticSeoUrlsOfTheRequestedAppsAreSynchronised(?string $appId): void
    {
        $synchronizer = $this->createMock(AppSeoUrlSynchronizer::class);
        $synchronizer->expects($this->once())->method('syncStaticRoutes')->with($appId);

        (new AppSeoUrlSyncHandler($synchronizer))(new AppSeoUrlSyncMessage($appId));
    }

    /**
     * @return iterable<string, array{?string}>
     */
    public static function messages(): iterable
    {
        yield 'a message for one app synchronises only that app' => [self::APP_ID];
        yield 'a message without an app synchronises all apps' => [null];
    }
}
