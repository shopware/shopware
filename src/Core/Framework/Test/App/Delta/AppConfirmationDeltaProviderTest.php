<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Delta;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Delta\AppConfirmationDeltaProvider;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class AppConfirmationDeltaProviderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testGetDeltas(): void
    {
        $deltas = $this->getAppConfirmationDeltaProvider()
            ->getDeltas(
                $this->getTestManifest(),
                new AppEntity()
            );

        static::assertCount(1, $deltas);
        static::assertArrayHasKey('permissions', $deltas);
        static::assertCount(6, $deltas['permissions']);
    }

    public function testRequiresRenewedConsent(): void
    {
        $appConfirmationDeltaProvider = $this->getAppConfirmationDeltaProvider();

        $requiresRenewedConsent = $appConfirmationDeltaProvider->requiresRenewedConsent(
            $this->getTestManifest(),
            new AppEntity()
        );
        static::assertTrue($requiresRenewedConsent);
    }

    protected function getAppLifecycle(): AppLifecycle
    {
        return $this->getContainer()->get(AppLifecycle::class);
    }

    protected function getAppRepository(): EntityRepository
    {
        return $this->getContainer()->get('app.repository');
    }

    protected function getTestManifest(): Manifest
    {
        return Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
    }

    protected function getAppConfirmationDeltaProvider(): AppConfirmationDeltaProvider
    {
        return $this->getContainer()
            ->get(AppConfirmationDeltaProvider::class);
    }
}
