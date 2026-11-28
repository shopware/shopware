<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Consent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Consent\AppConsentDefinitionProvider;
use Shopware\Core\Framework\App\Consent\ConsentConfig;
use Shopware\Core\Framework\App\Feature\AppFeature;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppConsentDefinitionProvider::class)]
class AppConsentDefinitionProviderTest extends TestCase
{
    public function testNoAppDeclaresConsents(): void
    {
        static::assertSame([], $this->createProvider()->getConsentDefinitions());
    }

    public function testConsentIsIdentifiedByAppAndName(): void
    {
        $definitions = $this->createProvider(
            $this->createAppFeature('swagApp', 'order_analysis', 'system'),
        )->getConsentDefinitions();

        static::assertCount(1, $definitions);
        static::assertSame('swagApp-order_analysis', $definitions[0]->getName());
        static::assertSame('system', $definitions[0]->getScopeName());
    }

    public function testSameConsentNameInTwoAppsStaysSeparate(): void
    {
        $definitions = $this->createProvider(
            $this->createAppFeature('swagApp', 'order_analysis', 'system'),
            $this->createAppFeature('otherApp', 'order_analysis', 'admin_user'),
        )->getConsentDefinitions();

        static::assertSame(
            ['swagApp-order_analysis', 'otherApp-order_analysis'],
            array_map(static fn ($definition) => $definition->getName(), $definitions),
        );
    }

    public function testRevisionAndSinceComeFromTheDeclarationNotTheRow(): void
    {
        $definitions = $this->createProvider(
            $this->createAppFeature('swagApp', 'order_analysis', 'system', new \DateTimeImmutable('2026-02-03'), '2026-01-01'),
        )->getConsentDefinitions();

        static::assertCount(1, $definitions);
        static::assertSame('2026-01-01', $definitions[0]->getLatestRevision());
        static::assertSame('2026-02-03', $definitions[0]->getSince()->format('Y-m-d'));
    }

    /**
     * @return AppFeature<ConsentConfig>
     */
    private function createAppFeature(
        string $appName,
        string $name,
        string $scope,
        ?\DateTimeImmutable $since = null,
        ?string $revision = null,
    ): AppFeature {
        return new AppFeature(
            Uuid::randomHex(),
            $appName,
            true,
            '1.0.0',
            true,
            new \DateTimeImmutable('2020-01-01'),
            new ConsentConfig($name, $scope, $since ?? new \DateTimeImmutable('2026-02-03'), $revision),
        );
    }

    /**
     * @param AppFeature<ConsentConfig> ...$features
     */
    private function createProvider(AppFeature ...$features): AppConsentDefinitionProvider
    {
        $storage = static::createStub(AppFeatureStorage::class);
        $storage->method('forActiveApps')->willReturn(array_values($features));

        return new AppConsentDefinitionProvider($storage);
    }
}
