<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlClaims;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlClaims::class)]
class AppSeoUrlClaimsTest extends TestCase
{
    private const APP_NAME = 'SwagSeoUrlApp';

    public function testPathsOfOtherAppsMapEveryNormalizedPathToTheAppDeclaringIt(): void
    {
        $claims = $this->claims([
            ['app_name' => 'SwagLegalApp', 'payload' => Json::encode(['name' => 'imprint', 'hook' => 'imprint', 'paths' => ['en-GB' => 'imprint', 'de-DE' => '/Impressum']])],
            ['app_name' => 'SwagFaqApp', 'payload' => Json::encode(['name' => 'faq', 'hook' => 'faq', 'paths' => ['en-GB' => 'FAQ']])],
        ]);

        static::assertSame(
            ['imprint' => 'SwagLegalApp', 'impressum' => 'SwagLegalApp', 'faq' => 'SwagFaqApp'],
            $claims->pathsOfOtherApps(self::APP_NAME)
        );
    }

    public function testPathsOfOtherAppsIgnoreStoredSeoUrlsWithoutUsablePaths(): void
    {
        $claims = $this->claims([
            ['app_name' => 'OtherApp', 'payload' => Json::encode(['name' => 'imprint'])],
            ['app_name' => 'OtherApp', 'payload' => Json::encode(['name' => 'legal', 'paths' => 'imprint'])],
            ['app_name' => 'OtherApp', 'payload' => Json::encode(['name' => 'about', 'paths' => ['en-GB' => ['imprint'], 'de-DE' => null]])],
        ]);

        static::assertSame([], $claims->pathsOfOtherApps(self::APP_NAME));
    }

    public function testHooksOfOtherAppsMapEveryHookToTheAppDeclaringIt(): void
    {
        $claims = $this->claims([
            ['app_name' => 'SwagLegalApp', 'payload' => Json::encode(['name' => 'imprint', 'hook' => 'legal-page', 'paths' => ['en-GB' => 'imprint']])],
            ['app_name' => 'SwagBlogApp', 'payload' => Json::encode(['name' => 'blog-detail', 'hook' => 'blog-post', 'entityName' => 'ce_blog', 'defaultTemplate' => 'blog/{{ ceBlog.title }}'])],
        ]);

        static::assertSame(
            ['legal-page' => 'SwagLegalApp', 'blog-post' => 'SwagBlogApp'],
            $claims->hooksOfOtherApps(self::APP_NAME)
        );
    }

    public function testHooksOfOtherAppsIgnoreStoredSeoUrlsWithoutAHook(): void
    {
        $claims = $this->claims([
            ['app_name' => 'OtherApp', 'payload' => Json::encode(['name' => 'imprint'])],
            ['app_name' => 'OtherApp', 'payload' => Json::encode(['name' => 'legal', 'hook' => ['legal-page']])],
        ]);

        static::assertSame([], $claims->hooksOfOtherApps(self::APP_NAME));
    }

    public function testNormalizePathDropsLeadingSlashesAndLowercases(): void
    {
        static::assertSame('legal/über-uns', AppSeoUrlClaims::normalizePath('//Legal/Über-Uns'));
    }

    /**
     * @param list<array{app_name: string, payload: string}> $rows
     */
    private function claims(array $rows): AppSeoUrlClaims
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        return new AppSeoUrlClaims($connection);
    }
}
