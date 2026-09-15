<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Handler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppActivationContext;
use Shopware\Core\Framework\App\Lifecycle\Context\AppRemovalContext;
use Shopware\Core\Framework\App\Lifecycle\Handler\SeoUrlRouteLifecycleHandler;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\SeoUrl;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
#[Package('framework')]
class SeoUrlRouteLifecycleHandlerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const IMPRINT_ROUTE = 'storefront.app.SwagSeoUrlApp.imprint';
    private const TEASER_ROUTE = 'storefront.app.SwagSeoUrlApp.product-teaser';

    private SeoUrlRouteLifecycleHandler $handler;

    private Connection $connection;

    private AppFixture $appFixture;

    protected function setUp(): void
    {
        $this->handler = static::getContainer()->get(SeoUrlRouteLifecycleHandler::class);
        $this->connection = static::getContainer()->get(Connection::class);

        $appFixture = static::getContainer()->get(AppFixture::class);
        static::assertInstanceOf(AppFixture::class, $appFixture);
        $this->appFixture = $appFixture;
    }

    public function testInstallPersistsTheDeclaredRoutesAndSeedsTheDefaultTemplate(): void
    {
        $manifest = $this->createManifest();
        $app = $this->appFixture->createApp($manifest);

        $this->handler->install($this->appFixture->createInstallContext($app, $manifest));

        $routes = $this->fetchRoutes($app->getId());
        static::assertSame(['imprint', 'product-teaser'], array_keys($routes));

        static::assertSame(self::IMPRINT_ROUTE, $routes['imprint']['route_name']);
        static::assertSame('imprint', $routes['imprint']['hook']);
        static::assertNull($routes['imprint']['entity_name']);
        static::assertNull($routes['imprint']['default_template']);
        static::assertSame(['en-GB' => 'swag-imprint'], json_decode((string) $routes['imprint']['paths'], true));
        static::assertSame(['en-GB' => 'Imprint'], json_decode((string) $routes['imprint']['label'], true));

        static::assertSame(self::TEASER_ROUTE, $routes['product-teaser']['route_name']);
        static::assertSame('product-teaser', $routes['product-teaser']['hook']);
        static::assertSame('product', $routes['product-teaser']['entity_name']);
        static::assertSame('{{ product.translated.name }}', $routes['product-teaser']['default_template']);
        static::assertNull($routes['product-teaser']['paths']);

        static::assertSame([
            'entityName' => 'product',
            'template' => '{{ product.translated.name }}',
            'isHeadless' => 0,
            'isValid' => 1,
        ], $this->fetchDefaultTemplate(self::TEASER_ROUTE));

        static::assertNull($this->fetchDefaultTemplate(self::IMPRINT_ROUTE));
    }

    public function testUpdateOverwritesADefaultTemplateThatWasNotChangedByTheMerchant(): void
    {
        [$app] = $this->install();

        $updated = $this->createManifest('{{ product.translated.name }}/{{ product.productNumber }}');
        $this->handler->update($this->appFixture->createUpdateContext($app, $updated));

        $template = $this->fetchDefaultTemplate(self::TEASER_ROUTE);
        static::assertIsArray($template);
        static::assertSame('{{ product.translated.name }}/{{ product.productNumber }}', $template['template']);

        $routes = $this->fetchRoutes($app->getId());
        static::assertSame('{{ product.translated.name }}/{{ product.productNumber }}', $routes['product-teaser']['default_template']);
    }

    public function testUpdateKeepsADefaultTemplateThatWasChangedByTheMerchant(): void
    {
        [$app] = $this->install();

        $this->connection->update(
            'seo_url_template',
            ['template' => 'teaser/{{ product.id }}'],
            ['route_name' => self::TEASER_ROUTE]
        );

        $updated = $this->createManifest('{{ product.translated.name }}/{{ product.productNumber }}');
        $this->handler->update($this->appFixture->createUpdateContext($app, $updated));

        $template = $this->fetchDefaultTemplate(self::TEASER_ROUTE);
        static::assertIsArray($template);
        static::assertSame('teaser/{{ product.id }}', $template['template']);
    }

    public function testUpdateRemovesRoutesThatAreNoLongerDeclared(): void
    {
        [$app] = $this->install();

        $this->createSeoUrl(self::IMPRINT_ROUTE);

        $updated = ManifestFixture::empty()->withName('SwagSeoUrlApp')->withSeoUrl($this->teaserSeoUrl());
        $this->handler->update($this->appFixture->createUpdateContext($app, $updated));

        static::assertSame(['product-teaser'], array_keys($this->fetchRoutes($app->getId())));
        static::assertSame([], $this->fetchSeoUrls(self::IMPRINT_ROUTE));
        static::assertNotNull($this->fetchDefaultTemplate(self::TEASER_ROUTE));
    }

    public function testDeactivateMarksTheGeneratedSeoUrlsAsDeleted(): void
    {
        [$app] = $this->install();

        $this->createSeoUrl(self::IMPRINT_ROUTE);
        $this->createSeoUrl(self::TEASER_ROUTE);

        $this->handler->deactivate(new AppActivationContext($app, Context::createDefaultContext()));

        static::assertSame([1], $this->fetchSeoUrls(self::IMPRINT_ROUTE));
        static::assertSame([1], $this->fetchSeoUrls(self::TEASER_ROUTE));
        static::assertNotNull($this->fetchDefaultTemplate(self::TEASER_ROUTE));
    }

    public function testUninstallRemovesTheGeneratedSeoUrlsAndTheDefaultTemplate(): void
    {
        [$app] = $this->install();

        $this->createSeoUrl(self::IMPRINT_ROUTE);
        $this->createSeoUrl(self::TEASER_ROUTE);

        $this->handler->uninstall(new AppRemovalContext($app, Context::createDefaultContext(), keepUserData: true));

        static::assertSame([], $this->fetchSeoUrls(self::IMPRINT_ROUTE));
        static::assertSame([], $this->fetchSeoUrls(self::TEASER_ROUTE));
        static::assertNull($this->fetchDefaultTemplate(self::TEASER_ROUTE));
    }

    /**
     * @return array{AppEntity}
     */
    private function install(): array
    {
        $manifest = $this->createManifest();
        $app = $this->appFixture->createApp($manifest);

        $this->handler->install($this->appFixture->createInstallContext($app, $manifest));

        return [$app];
    }

    private function createManifest(string $template = '{{ product.translated.name }}'): ManifestFixture
    {
        return ManifestFixture::empty()
            ->withName('SwagSeoUrlApp')
            ->withSeoUrl(SeoUrl::fromArray([
                'name' => 'imprint',
                'label' => ['en-GB' => 'Imprint'],
                'path' => ['en-GB' => 'swag-imprint'],
            ]))
            ->withSeoUrl($this->teaserSeoUrl($template));
    }

    private function teaserSeoUrl(string $template = '{{ product.translated.name }}'): SeoUrl
    {
        return SeoUrl::fromArray([
            'name' => 'product-teaser',
            'entity' => 'product',
            'label' => ['en-GB' => 'Product teaser'],
            'defaultTemplate' => $template,
        ]);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fetchRoutes(string $appId): array
    {
        return $this->connection->fetchAllAssociativeIndexed(
            'SELECT `name`, `route_name`, `hook`, `entity_name`, `default_template`, `paths`, `label`
             FROM `app_seo_url_route` WHERE `app_id` = :appId ORDER BY `name`',
            ['appId' => Uuid::fromHexToBytes($appId)]
        );
    }

    /**
     * @return array{entityName: string, template: string, isHeadless: int, isValid: int}|null
     */
    private function fetchDefaultTemplate(string $routeName): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT `entity_name`, `template`, `is_headless`, `is_valid`
             FROM `seo_url_template` WHERE `route_name` = :routeName AND `sales_channel_id` IS NULL',
            ['routeName' => $routeName]
        );

        if ($row === false) {
            return null;
        }

        return [
            'entityName' => (string) $row['entity_name'],
            'template' => (string) $row['template'],
            'isHeadless' => (int) $row['is_headless'],
            'isValid' => (int) $row['is_valid'],
        ];
    }

    /**
     * @return list<int>
     */
    private function fetchSeoUrls(string $routeName): array
    {
        return array_map(
            static fn (mixed $isDeleted): int => (int) $isDeleted,
            $this->connection->fetchFirstColumn(
                'SELECT `is_deleted` FROM `seo_url` WHERE `route_name` = :routeName',
                ['routeName' => $routeName]
            )
        );
    }

    private function createSeoUrl(string $routeName): void
    {
        $this->connection->insert('seo_url', [
            'id' => Uuid::randomBytes(),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'foreign_key' => Uuid::randomBytes(),
            'route_name' => $routeName,
            'path_info' => '/storefront/script/test',
            'seo_path_info' => 'swag-' . Uuid::randomHex(),
            'is_canonical' => 1,
            'is_modified' => 1,
            'is_deleted' => 0,
            'created_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
