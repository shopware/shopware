<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\SystemCheck;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopId\Fingerprint\AppUrl;
use Shopware\Core\Framework\App\ShopId\Fingerprint\InstallationPath;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\ShopId\FingerprintMatch;
use Shopware\Core\Framework\App\ShopId\FingerprintMismatch;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\App\SystemCheck\ShopIdCheck;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\Category;
use Shopware\Core\Framework\SystemCheck\Check\Status;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShopIdCheck::class)]
class ShopIdCheckTest extends TestCase
{
    public function testTheShopIdentityIsUnchangedWhenTheFingerprintsMatch(): void
    {
        $result = $this->check(new FingerprintComparisonResult([], [], 75))->run();

        static::assertSame(Status::OK, $result->status);
        static::assertTrue($result->healthy);
        static::assertSame([], $result->extra);
    }

    public function testTheShopIdentityIsUnchangedWithoutAStoredShopId(): void
    {
        static::assertSame(Status::OK, $this->check(null)->run()->status);
    }

    public function testAChangedIdentityPutsTheRegisteredAppsOnHold(): void
    {
        $result = $this->check($this->changedAppUrl(), ['Acme Payments'])->run();

        static::assertSame(Status::ERROR, $result->status);
        static::assertFalse($result->healthy);
        static::assertSame([
            'fingerprints' => [
                ['identifier' => AppUrl::IDENTIFIER, 'storedValue' => 'https://old.example', 'currentValue' => 'https://new.example', 'matches' => false],
                ['identifier' => InstallationPath::IDENTIFIER, 'storedValue' => '/var/www/html', 'currentValue' => '/var/www/html', 'matches' => true],
            ],
            'appsOnHold' => ['Acme Payments'],
        ], $result->extra);
    }

    public function testAChangedIdentityIsOnlyAWarningWithoutRegisteredApps(): void
    {
        $result = $this->check($this->changedAppUrl())->run();

        static::assertSame(Status::WARNING, $result->status);
        static::assertTrue($result->healthy);
        static::assertArrayNotHasKey('appsOnHold', $result->extra);
    }

    public function testAMissingAppUrlIsAnError(): void
    {
        $shopIdProvider = static::createStub(ShopIdProvider::class);
        $shopIdProvider->method('compareFingerprints')->willThrowException(AppException::appUrlNotConfigured());

        $result = (new ShopIdCheck($shopIdProvider, static::createStub(Connection::class)))->run();

        static::assertSame(Status::ERROR, $result->status);
        static::assertSame('APP_URL is missing or invalid', $result->message);
    }

    public function testItIsAQuickAppCheck(): void
    {
        $check = $this->check(null);

        static::assertSame('ShopId', $check->name());
        static::assertSame(Category::EXTERNAL, $check->category());
        static::assertSame(['apps'], $check->tags());
        static::assertTrue($check->allowedToRunIn(SystemCheckExecutionContext::WEB));
        static::assertFalse($check->allowedToRunIn(SystemCheckExecutionContext::PRE_ROLLOUT));
    }

    /**
     * @param list<string> $registeredApps
     */
    private function check(?FingerprintComparisonResult $comparison, array $registeredApps = []): ShopIdCheck
    {
        $shopIdProvider = static::createStub(ShopIdProvider::class);
        $shopIdProvider->method('compareFingerprints')->willReturn($comparison);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn($registeredApps);

        return new ShopIdCheck($shopIdProvider, $connection);
    }

    private function changedAppUrl(): FingerprintComparisonResult
    {
        return new FingerprintComparisonResult(
            [InstallationPath::IDENTIFIER => new FingerprintMatch(InstallationPath::IDENTIFIER, '/var/www/html', 100)],
            [AppUrl::IDENTIFIER => new FingerprintMismatch(AppUrl::IDENTIFIER, 'https://old.example', 'https://new.example', 100)],
            75,
        );
    }
}
