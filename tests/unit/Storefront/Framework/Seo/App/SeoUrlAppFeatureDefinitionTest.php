<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\EntitySeoUrl;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\SeoUrl;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Validation\RouteBlocklistService;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Util\Json;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlConfig;
use Shopware\Storefront\Framework\Seo\App\SeoUrlAppFeatureDefinition;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlAppFeatureDefinition::class)]
class SeoUrlAppFeatureDefinitionTest extends TestCase
{
    private const APP_NAME = 'SwagSeoUrlApp';

    private SeoUrlAppFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new SeoUrlAppFeatureDefinition(
            static::createStub(Connection::class),
            static::createStub(RouteBlocklistService::class),
        );
    }

    public function testGetTypeReturnsStorefrontSeoUrl(): void
    {
        static::assertSame('storefront_seo_url', $this->definition->getType());
    }

    public function testGetConfigClassReturnsAppSeoUrlConfig(): void
    {
        static::assertSame(AppSeoUrlConfig::class, $this->definition->getConfigClass());
    }

    public function testFromAppMapsDeclaredSeoUrlsAndBackfillsMissingDefaultLocalePath(): void
    {
        $manifest = ManifestFixture::empty()
            ->withName(self::APP_NAME)
            ->withSeoUrl(SeoUrl::fromArray(['name' => 'imprint', 'path' => ['en-GB' => 'imprint', 'de-DE' => 'impressum']]))
            ->withSeoUrl(SeoUrl::fromArray(['name' => 'contact', 'hook' => 'contact-form', 'path' => ['de-DE' => 'kontakt']]));

        static::assertEquals(
            [
                new AppSeoUrlConfig(
                    name: 'imprint',
                    routeName: 'storefront.app.SwagSeoUrlApp.imprint',
                    hook: 'imprint',
                    paths: ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
                ),
                new AppSeoUrlConfig(
                    name: 'contact',
                    routeName: 'storefront.app.SwagSeoUrlApp.contact',
                    hook: 'contact-form',
                    paths: ['de-DE' => 'kontakt', 'en-GB' => 'kontakt'],
                ),
            ],
            $this->definition->fromApp($manifest, new Filesystem(''), 'en-GB')
        );
    }

    public function testFromAppBackfillsDefaultLocaleFromEnglishPathWhenShopDefaultDiffers(): void
    {
        $manifest = ManifestFixture::empty()
            ->withName(self::APP_NAME)
            ->withSeoUrl(SeoUrl::fromArray(['name' => 'imprint', 'path' => ['de-DE' => 'impressum', 'en-GB' => 'imprint']]));

        $configs = $this->definition->fromApp($manifest, new Filesystem(''), 'fr-FR');

        static::assertCount(1, $configs);
        static::assertSame(['de-DE' => 'impressum', 'en-GB' => 'imprint', 'fr-FR' => 'imprint'], $configs[0]->getPaths());
    }

    public function testFromAppIgnoresEntitySeoUrls(): void
    {
        $manifest = ManifestFixture::empty()
            ->withName(self::APP_NAME)
            ->withEntitySeoUrl(EntitySeoUrl::fromArray([
                'name' => 'product-teaser',
                'entity' => 'product',
                'defaultTemplate' => 'teaser/{{ product.productNumber }}',
            ]));

        static::assertSame([], $this->definition->fromApp($manifest, new Filesystem(''), 'en-GB'));
    }

    public function testFromAppReturnsEmptyListWhenManifestDeclaresNoStorefront(): void
    {
        static::assertSame([], $this->definition->fromApp(ManifestFixture::empty(), new Filesystem(''), 'en-GB'));
    }

    public function testToPayloadAndFromPayloadRoundTrip(): void
    {
        $config = new AppSeoUrlConfig(
            name: 'imprint',
            routeName: 'storefront.app.SwagSeoUrlApp.imprint',
            hook: 'legal-page',
            paths: ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
        );

        $payload = $this->definition->toPayload($config, null);

        static::assertSame([
            'name' => 'imprint',
            'routeName' => 'storefront.app.SwagSeoUrlApp.imprint',
            'hook' => 'legal-page',
            'paths' => ['en-GB' => 'imprint', 'de-DE' => 'impressum'],
        ], $payload);

        static::assertEquals($config, $this->definition->fromPayload($payload));
    }

    public function testToPayloadTakesTheDeclaredPathsOverTheStoredOnesOnUpdate(): void
    {
        $stored = self::config('imprint', ['en-GB' => 'imprint']);
        $declared = self::config('imprint', ['en-GB' => 'legal-notice']);

        static::assertSame(['en-GB' => 'legal-notice'], $this->definition->toPayload($declared, $stored)['paths']);
    }

    public function testValidateSkipsAllLookupsWhenNoSeoUrlsAreDeclared(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $connection->expects($this->never())->method('fetchOne');

        $routeBlocklist = $this->createMock(RouteBlocklistService::class);
        $routeBlocklist->expects($this->never())->method('isPathBlocked');

        (new SeoUrlAppFeatureDefinition($connection, $routeBlocklist))->validate([], $this->persistContext());
    }

    public function testValidateSkipsTheSeoUrlLookupWhenTheSeoUrlsDeclareNoPath(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([]);
        $connection->expects($this->never())->method('fetchOne');

        (new SeoUrlAppFeatureDefinition($connection, static::createStub(RouteBlocklistService::class)))
            ->validate([self::config('imprint', [])], $this->persistContext());
    }

    /**
     * @param list<AppSeoUrlConfig> $configs
     * @param array<string, array<string, string>> $pathsOfOtherApps app name => declared paths
     * @param list<string> $blockedPaths
     */
    #[DataProvider('rejectedDeclarations')]
    public function testValidateRejectsTheDeclaration(
        array $configs,
        SeoException $expected,
        array $pathsOfOtherApps = [],
        array $blockedPaths = [],
        ?string $pathUsedBySeoUrls = null,
    ): void {
        $definition = $this->buildDefinition($pathsOfOtherApps, $blockedPaths, $pathUsedBySeoUrls);

        $this->expectExceptionObject($expected);

        $definition->validate($configs, $this->persistContext());
    }

    /**
     * @return iterable<string, array{configs: list<AppSeoUrlConfig>, expected: SeoException, pathsOfOtherApps?: array<string, array<string, string>>, blockedPaths?: list<string>, pathUsedBySeoUrls?: string}>
     */
    public static function rejectedDeclarations(): iterable
    {
        yield 'a path with a fragment marker is not URL-safe' => [
            'configs' => [self::config('imprint', ['en-GB' => 'imprint#legal'])],
            'expected' => SeoException::appSeoUrlPathInvalid('imprint', 'imprint#legal'),
        ];

        yield 'a path with a raw space would be rewritten when stored' => [
            'configs' => [self::config('imprint', ['en-GB' => 'legal notice'])],
            'expected' => SeoException::appSeoUrlPathInvalid('imprint', 'legal notice'),
        ];

        yield 'the path of every locale is checked' => [
            'configs' => [self::config('imprint', ['en-GB' => 'imprint', 'de-DE' => 'impressum#top'])],
            'expected' => SeoException::appSeoUrlPathInvalid('imprint', 'impressum#top'),
        ];

        yield 'a path matched by a storefront route is in use' => [
            'configs' => [self::config('my-account', ['en-GB' => 'account'])],
            'expected' => SeoException::appSeoUrlPathInUse('my-account', 'account'),
            'blockedPaths' => ['account'],
        ];

        yield 'a storefront route is matched regardless of case and leading slash' => [
            'configs' => [self::config('my-account', ['en-GB' => '/Account'])],
            'expected' => SeoException::appSeoUrlPathInUse('my-account', '/Account'),
            'blockedPaths' => ['account'],
        ];

        yield 'a path declared by another app is already registered' => [
            'configs' => [self::config('imprint', ['en-GB' => 'imprint'])],
            'expected' => SeoException::appSeoUrlPathAlreadyRegistered('imprint', 'imprint', 'OtherApp'),
            'pathsOfOtherApps' => ['OtherApp' => ['en-GB' => 'imprint']],
        ];

        yield 'another app claims a path regardless of case and leading slash' => [
            'configs' => [self::config('imprint', ['de-DE' => 'IMPRESSUM'])],
            'expected' => SeoException::appSeoUrlPathAlreadyRegistered('imprint', 'IMPRESSUM', 'OtherApp'),
            'pathsOfOtherApps' => ['OtherApp' => ['en-GB' => 'about', 'de-DE' => '/Impressum']],
        ];

        yield 'two SEO URLs of the app sharing a path' => [
            'configs' => [
                self::config('imprint', ['en-GB' => 'imprint']),
                self::config('legal-notice', ['en-GB' => 'imprint']),
            ],
            'expected' => SeoException::appSeoUrlPathAlreadyRegistered('legal-notice', 'imprint', self::APP_NAME),
        ];

        yield 'two SEO URLs of the app sharing a path across locales, case and leading slash' => [
            'configs' => [
                self::config('imprint', ['en-GB' => 'imprint', 'de-DE' => 'impressum']),
                self::config('legal-notice', ['en-GB' => '/Impressum']),
            ],
            'expected' => SeoException::appSeoUrlPathAlreadyRegistered('legal-notice', '/Impressum', self::APP_NAME),
        ];

        yield 'a path used by the SEO URL of another route' => [
            'configs' => [self::config('imprint', ['en-GB' => 'imprint'])],
            'expected' => SeoException::appSeoUrlPathInUse('imprint', 'imprint'),
            'pathUsedBySeoUrls' => 'imprint',
        ];

        yield 'a used path is reported for the SEO URL declaring it' => [
            'configs' => [
                self::config('imprint', ['en-GB' => 'imprint']),
                self::config('legal-notice', ['en-GB' => '/Legal']),
            ],
            'expected' => SeoException::appSeoUrlPathInUse('legal-notice', '/Legal'),
            'pathUsedBySeoUrls' => 'legal',
        ];
    }

    /**
     * @param list<AppSeoUrlConfig> $configs
     * @param array<string, array<string, string>> $pathsOfOtherApps app name => declared paths
     */
    #[DataProvider('acceptedDeclarations')]
    public function testValidateAcceptsTheDeclaration(array $configs, array $pathsOfOtherApps = []): void
    {
        $definition = $this->buildDefinition($pathsOfOtherApps);

        $this->expectNotToPerformAssertions();

        $definition->validate($configs, $this->persistContext());
    }

    /**
     * @return iterable<string, array{configs: list<AppSeoUrlConfig>, pathsOfOtherApps?: array<string, array<string, string>>}>
     */
    public static function acceptedDeclarations(): iterable
    {
        yield 'paths no other app, route or SEO URL uses' => [
            'configs' => [
                self::config('imprint', ['en-GB' => 'imprint', 'de-DE' => 'impressum']),
                self::config('contact', ['en-GB' => 'contact']),
            ],
            'pathsOfOtherApps' => ['OtherApp' => ['en-GB' => 'faq']],
        ];

        yield 'the same path in two locales of one SEO URL' => [
            'configs' => [self::config('imprint', ['en-GB' => 'imprint', 'de-DE' => 'imprint'])],
        ];

        yield 'the same path in two locales of one SEO URL differing in case and leading slash' => [
            'configs' => [self::config('imprint', ['en-GB' => 'imprint', 'de-DE' => '/Imprint'])],
        ];

        yield 'non-ASCII characters and valid percent-escapes are URL-safe' => [
            'configs' => [self::config('about', ['en-GB' => 'caf%C3%A9', 'de-DE' => 'über-uns'])],
        ];
    }

    public function testValidateIgnoresStoredSeoUrlsOfOtherAppsWithoutUsablePaths(): void
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['app_name' => 'OtherApp', 'payload' => Json::encode(['name' => 'imprint'])],
            ['app_name' => 'OtherApp', 'payload' => Json::encode(['name' => 'legal', 'paths' => 'imprint'])],
            ['app_name' => 'OtherApp', 'payload' => Json::encode(['name' => 'about', 'paths' => ['en-GB' => ['imprint'], 'de-DE' => null]])],
        ]);
        $connection->method('fetchOne')->willReturn(false);

        $definition = new SeoUrlAppFeatureDefinition($connection, static::createStub(RouteBlocklistService::class));

        $this->expectNotToPerformAssertions();

        $definition->validate([self::config('imprint', ['en-GB' => 'imprint'])], $this->persistContext());
    }

    /**
     * @param array<string, array<string, string>> $pathsOfOtherApps app name => declared paths
     * @param list<string> $blockedPaths
     */
    private function buildDefinition(
        array $pathsOfOtherApps = [],
        array $blockedPaths = [],
        ?string $pathUsedBySeoUrls = null,
    ): SeoUrlAppFeatureDefinition {
        $otherAppRows = [];
        foreach ($pathsOfOtherApps as $appName => $paths) {
            $otherAppRows[] = [
                'app_name' => $appName,
                'payload' => Json::encode($this->definition->toPayload(
                    new AppSeoUrlConfig('other-seo-url', 'storefront.app.' . $appName . '.other-seo-url', 'other-seo-url', $paths),
                    null
                )),
            ];
        }

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($otherAppRows);
        $connection->method('fetchOne')->willReturnCallback(
            /**
             * @param array<string, mixed> $params
             */
            static fn (string $sql, array $params): int|false => ($params['path'] ?? null) === $pathUsedBySeoUrls ? 1 : false
        );

        $routeBlocklist = static::createStub(RouteBlocklistService::class);
        $routeBlocklist->method('isPathBlocked')->willReturnCallback(
            static fn (string $path): bool => \in_array($path, $blockedPaths, true)
        );

        return new SeoUrlAppFeatureDefinition($connection, $routeBlocklist);
    }

    private function persistContext(): AppPersistContext
    {
        return AppFixture::createInstallContext(
            AppFixture::createAppEntity(self::APP_NAME),
            ManifestFixture::empty()->withName(self::APP_NAME),
        );
    }

    /**
     * @param array<string, string> $paths
     */
    private static function config(string $name, array $paths): AppSeoUrlConfig
    {
        return new AppSeoUrlConfig($name, 'storefront.app.' . self::APP_NAME . '.' . $name, $name, $paths);
    }
}
