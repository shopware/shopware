<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Telemetry;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\App\Telemetry\AppTelemetryConfig;
use Shopware\Core\Framework\App\Telemetry\AppTelemetryConfigLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
#[Package('framework')]
class AppTelemetryClaimsTest extends TestCase
{
    use AppSystemTestBehaviour;
    use IntegrationTestBehaviour;

    private const APP_NAME = 'swagIntegrationTestTelemetryApp';

    protected function setUp(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/TelemetryApp');
    }

    public function testInstallPersistsTheAcceptedClaimsAsOneAppFeatureRow(): void
    {
        $rows = static::getContainer()->get(Connection::class)->fetchAllAssociative(
            'SELECT `app_feature`.`type`, `app_feature`.`name`, `app_feature`.`payload` FROM `app_feature`
             INNER JOIN `app` ON `app`.`id` = `app_feature`.`app_id`
             WHERE `app`.`name` = :appName',
            ['appName' => self::APP_NAME],
        );

        static::assertCount(1, $rows);
        static::assertSame('telemetry', $rows[0]['type']);
        static::assertSame(AppTelemetryConfig::NAME, $rows[0]['name']);
        // the unknown mapping and the config-declared `product` key are skipped at install
        static::assertSame(
            ['mappings' => ['entity_group' => ['ce_swag_blog_*'], 'flow_trigger_group' => ['swag.blog.published']]],
            Json::decodeToArray((string) $rows[0]['payload']),
        );
    }

    public function testClaimedKeysResolveToTheAppNameBehindConfigAndCodeLevelRules(): void
    {
        // a fresh registry on the container's storage and the shipped tables: the shared service
        // memoizes app claims per process, which would make this test order-dependent
        $mappings = static::getContainer()->getParameter('shopware.telemetry.metrics.mappings');
        static::assertIsArray($mappings);

        $registry = new LabelMappingRegistry(
            $mappings,
            'other',
            fn (): AppTelemetryConfigLoader => new AppTelemetryConfigLoader(
                fn (): AppFeatureStorage => static::getContainer()->get(AppFeatureStorage::class),
                new NullLogger(),
            ),
        );

        static::assertSame('swag_integration_test_telemetry_app', $registry->resolve(EntityGroupResolver::MAPPING, 'ce_swag_blog_post'));
        static::assertSame('product', $registry->resolve(EntityGroupResolver::MAPPING, 'product'));
        static::assertSame('other', $registry->resolve(EntityGroupResolver::MAPPING, 'ce_unclaimed'));
    }
}
