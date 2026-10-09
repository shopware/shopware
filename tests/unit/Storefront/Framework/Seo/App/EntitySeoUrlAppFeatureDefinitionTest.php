<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\SeoException;
use Shopware\Core\Content\Seo\SeoUrlGenerator;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;
use Shopware\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\EntitySeoUrl;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\SeoUrl;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Framework\Seo\App\AppEntitySeoUrlConfig;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlClaims;
use Shopware\Storefront\Framework\Seo\App\EntitySeoUrlAppFeatureDefinition;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(EntitySeoUrlAppFeatureDefinition::class)]
class EntitySeoUrlAppFeatureDefinitionTest extends TestCase
{
    private const APP_NAME = 'SwagSeoUrlApp';

    private const TEASER_ROUTE = 'storefront.app.SwagSeoUrlApp.product-teaser';

    private const SALES_CHANNEL_ID = 'ffffffffffffffffffffffffffffffff';

    private StaticDefinitionInstanceRegistry $definitionRegistry;

    private EntitySeoUrlAppFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definitionRegistry = new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class,
                ProductTranslationDefinition::class,
                ProductCategoryDefinition::class,
                ProductManufacturerDefinition::class,
                ProductManufacturerTranslationDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $this->definition = $this->buildDefinition(StaticEntityRepository::of(SeoUrlTemplateCollection::class));
    }

    public function testGetTypeReturnsStorefrontEntitySeoUrl(): void
    {
        static::assertSame('storefront_entity_seo_url', $this->definition->getType());
    }

    public function testGetConfigClassReturnsAppEntitySeoUrlConfig(): void
    {
        static::assertSame(AppEntitySeoUrlConfig::class, $this->definition->getConfigClass());
    }

    public function testFromAppMapsDeclaredEntitySeoUrlsAndIgnoresStaticOnes(): void
    {
        $manifest = ManifestFixture::empty()
            ->withName(self::APP_NAME)
            ->withSeoUrl(SeoUrl::fromArray(['name' => 'imprint', 'path' => ['en-GB' => 'imprint']]))
            ->withEntitySeoUrl(EntitySeoUrl::fromArray([
                'name' => 'product-teaser',
                'entity' => 'product',
                'defaultTemplate' => 'teaser/{{ product.productNumber }}',
            ]))
            ->withEntitySeoUrl(EntitySeoUrl::fromArray([
                'name' => 'blog-detail',
                'hook' => 'blog-post',
                'entity' => 'ce_blog',
                'defaultTemplate' => 'blog/{{ ceBlog.translated.title }}',
            ]));

        static::assertEquals(
            [
                new AppEntitySeoUrlConfig(
                    name: 'product-teaser',
                    routeName: self::TEASER_ROUTE,
                    hook: 'product-teaser',
                    entityName: 'product',
                    defaultTemplate: 'teaser/{{ product.productNumber }}',
                ),
                new AppEntitySeoUrlConfig(
                    name: 'blog-detail',
                    routeName: 'storefront.app.SwagSeoUrlApp.blog-detail',
                    hook: 'blog-post',
                    entityName: 'ce_blog',
                    defaultTemplate: 'blog/{{ ceBlog.translated.title }}',
                ),
            ],
            $this->definition->fromApp($manifest, new Filesystem(''), 'en-GB')
        );
    }

    public function testFromAppReturnsEmptyListWhenManifestDeclaresNoStorefront(): void
    {
        static::assertSame([], $this->definition->fromApp(ManifestFixture::empty(), new Filesystem(''), 'en-GB'));
    }

    public function testToPayloadAndFromPayloadRoundTrip(): void
    {
        $config = self::config();

        $payload = $this->definition->toPayload($config, null);

        static::assertSame([
            'name' => 'product-teaser',
            'routeName' => self::TEASER_ROUTE,
            'hook' => 'teaser-page',
            'entityName' => 'product',
            'defaultTemplate' => 'teaser/{{ product.productNumber }}',
        ], $payload);

        static::assertEquals($config, $this->definition->fromPayload($payload));
    }

    public function testToPayloadTakesTheDeclaredConfigOverTheStoredOneOnUpdate(): void
    {
        $stored = self::config(entityName: 'category', defaultTemplate: 'teaser/{{ category.name }}');

        $payload = $this->definition->toPayload(self::config(), $stored);

        static::assertSame('product', $payload['entityName']);
        static::assertSame('teaser/{{ product.productNumber }}', $payload['defaultTemplate']);
    }

    public function testValidateSkipsTheHookLookupWhenNoEntitySeoUrlsAreDeclared(): void
    {
        $claims = $this->createMock(AppSeoUrlClaims::class);
        $claims->expects($this->never())->method('hooksOfOtherApps');

        $this->buildDefinition(StaticEntityRepository::of(SeoUrlTemplateCollection::class), $claims)
            ->validate([], $this->persistContext());
    }

    /**
     * @param list<AppEntitySeoUrlConfig> $configs
     * @param array<string, list<string>>|null $permissions entity name => privileges, null when the app declares none
     * @param array<string, string> $hooksOfOtherApps hook => app name
     */
    #[DataProvider('rejectedDeclarations')]
    public function testValidateRejectsTheDeclaration(
        array $configs,
        ?array $permissions,
        SeoException $expected,
        array $hooksOfOtherApps = [],
    ): void {
        $definition = $this->buildDefinition(StaticEntityRepository::of(SeoUrlTemplateCollection::class), $this->claims($hooksOfOtherApps));

        $this->expectExceptionObject($expected);

        $definition->validate($configs, $this->persistContext($permissions));
    }

    /**
     * @return iterable<string, array{configs: list<AppEntitySeoUrlConfig>, permissions: array<string, list<string>>|null, expected: SeoException, hooksOfOtherApps?: array<string, string>}>
     */
    public static function rejectedDeclarations(): iterable
    {
        yield 'an entity the shop does not know' => [
            'configs' => [self::config(entityName: 'unknown_entity', defaultTemplate: 'teaser/{{ unknownEntity.name }}')],
            'permissions' => ['unknown_entity' => ['read']],
            'expected' => SeoException::appEntitySeoUrlEntityUnsupported('product-teaser', 'unknown_entity'),
        ];

        yield 'a mapping entity has no page of its own' => [
            'configs' => [self::config(entityName: 'product_category', defaultTemplate: 'teaser/{{ productCategory.productId }}')],
            'permissions' => ['product_category' => ['read']],
            'expected' => SeoException::appEntitySeoUrlEntityUnsupported('product-teaser', 'product_category'),
        ];

        yield 'a translation entity has no page of its own' => [
            'configs' => [self::config(entityName: 'product_translation', defaultTemplate: 'teaser/{{ productTranslation.name }}')],
            'permissions' => ['product_translation' => ['read']],
            'expected' => SeoException::appEntitySeoUrlEntityUnsupported('product-teaser', 'product_translation'),
        ];

        yield 'an app without permissions may not read the entity' => [
            'configs' => [self::config()],
            'permissions' => null,
            'expected' => SeoException::appEntitySeoUrlNotPermitted('product-teaser', 'product', ['product:read']),
        ];

        yield 'an association the default template uses needs its own read permission' => [
            'configs' => [self::config(defaultTemplate: 'teaser/{{ product.manufacturer.translated.name }}')],
            'permissions' => ['product' => ['read']],
            'expected' => SeoException::appEntitySeoUrlNotPermitted('product-teaser', 'product', ['product_manufacturer:read']),
        ];

        yield 'a custom entity that is not registered yet needs the read permission' => [
            'configs' => [self::config(name: 'blog-detail', entityName: 'ce_blog', defaultTemplate: 'blog/{{ ceBlog.title }}')],
            'permissions' => ['product' => ['read']],
            'expected' => SeoException::appEntitySeoUrlNotPermitted('blog-detail', 'ce_blog', ['ce_blog:read']),
        ];

        yield 'a hook used by the SEO URL of another app is already registered' => [
            'configs' => [self::config()],
            'permissions' => ['product' => ['read']],
            'expected' => SeoException::appSeoUrlHookAlreadyRegistered('product-teaser', 'teaser-page', 'OtherApp'),
            'hooksOfOtherApps' => ['teaser-page' => 'OtherApp'],
        ];

        yield 'two entity SEO URLs of the app sharing a hook' => [
            'configs' => [self::config(), self::config(name: 'product-detail')],
            'permissions' => ['product' => ['read']],
            'expected' => SeoException::appSeoUrlHookAlreadyRegistered('product-detail', 'teaser-page', self::APP_NAME),
        ];
    }

    /**
     * @param list<AppEntitySeoUrlConfig> $configs
     * @param array<string, list<string>> $permissions entity name => privileges
     * @param array<string, string> $hooksOfOtherApps hook => app name
     */
    #[DataProvider('acceptedDeclarations')]
    public function testValidateAcceptsTheDeclaration(array $configs, array $permissions, array $hooksOfOtherApps = []): void
    {
        $definition = $this->buildDefinition(StaticEntityRepository::of(SeoUrlTemplateCollection::class), $this->claims($hooksOfOtherApps));

        $this->expectNotToPerformAssertions();

        $definition->validate($configs, $this->persistContext($permissions));
    }

    /**
     * @return iterable<string, array{configs: list<AppEntitySeoUrlConfig>, permissions: array<string, list<string>>, hooksOfOtherApps?: array<string, string>}>
     */
    public static function acceptedDeclarations(): iterable
    {
        yield 'an entity and the associations of its default template the app may read' => [
            'configs' => [self::config(defaultTemplate: 'teaser/{{ product.manufacturer.translated.name }}')],
            'permissions' => ['product' => ['read'], 'product_manufacturer' => ['read']],
        ];

        yield 'a custom entity that is not registered yet but the app may read' => [
            'configs' => [self::config(name: 'blog-detail', entityName: 'ce_blog', defaultTemplate: 'blog/{{ ceBlog.title }}')],
            'permissions' => ['ce_blog' => ['read']],
        ];

        yield 'a custom entity with the long prefix that is not registered yet but the app may read' => [
            'configs' => [self::config(name: 'blog-detail', entityName: 'custom_entity_blog', defaultTemplate: 'blog/{{ customEntityBlog.title }}')],
            'permissions' => ['custom_entity_blog' => ['read']],
        ];

        yield 'hooks no other app uses' => [
            'configs' => [self::config(), self::config(name: 'product-detail', hook: 'product-page')],
            'permissions' => ['product' => ['read']],
            'hooksOfOtherApps' => ['faq' => 'OtherApp'],
        ];
    }

    public function testPersistedCreatesTheDefaultTemplateWhenTheRouteHasNone(): void
    {
        $repository = StaticEntityRepository::of(SeoUrlTemplateCollection::class, [
            static function (Criteria $criteria): SeoUrlTemplateCollection {
                static::assertEquals([new EqualsFilter('routeName', self::TEASER_ROUTE)], $criteria->getFilters());

                return new SeoUrlTemplateCollection();
            },
        ]);

        $this->buildDefinition($repository)->persisted([self::config()], $this->persistContext());

        static::assertSame([[
            'routeName' => self::TEASER_ROUTE,
            'entityName' => 'product',
            'template' => 'teaser/{{ product.productNumber }}',
            'isValid' => true,
            'isHeadless' => false,
        ]], $repository->getPayloads(StaticEntityRepository::CREATE));
        static::assertSame([], $repository->deletes);
    }

    public function testPersistedKeepsTheDefaultAndTheOverridesForTheSameEntity(): void
    {
        $repository = new StaticEntityRepository([
            new SeoUrlTemplateCollection([
                $this->template(entityName: 'product', template: 'shop-teaser/{{ product.productNumber }}', salesChannelId: self::SALES_CHANNEL_ID),
                $this->template(entityName: 'product', template: 'my-teaser/{{ product.translated.name }}'),
            ]),
        ]);

        $this->buildDefinition($repository)->persisted([self::config()], $this->persistContext());

        static::assertSame([], $repository->creates);
        static::assertSame([], $repository->updates);
        static::assertSame([], $repository->deletes);
    }

    public function testPersistedReplacesEveryTemplateOfTheRouteWhenTheDefaultIsBoundToAnotherEntity(): void
    {
        $default = $this->template(entityName: 'category', template: 'teaser/{{ category.translated.name }}');
        $override = $this->template(entityName: 'category', template: 'my-teaser/{{ category.translated.name }}', salesChannelId: self::SALES_CHANNEL_ID);
        $repository = new StaticEntityRepository([new SeoUrlTemplateCollection([$default, $override])]);

        $this->buildDefinition($repository)->persisted([self::config()], $this->persistContext());

        static::assertSame([[['id' => $default->getId()], ['id' => $override->getId()]]], $repository->deletes);
        static::assertSame([[
            'routeName' => self::TEASER_ROUTE,
            'entityName' => 'product',
            'template' => 'teaser/{{ product.productNumber }}',
            'isValid' => true,
            'isHeadless' => false,
        ]], $repository->getPayloads(StaticEntityRepository::CREATE));
    }

    public function testPersistedSeedsTheMissingTemplateOfEveryDeclaredEntitySeoUrl(): void
    {
        $repository = new StaticEntityRepository([
            new SeoUrlTemplateCollection([$this->template(entityName: 'product', template: 'teaser/{{ product.productNumber }}')]),
            new SeoUrlTemplateCollection(),
        ]);

        $this->buildDefinition($repository)->persisted(
            [
                self::config(),
                self::config(name: 'blog-detail', entityName: 'ce_blog', defaultTemplate: 'blog/{{ ceBlog.translated.title }}'),
            ],
            $this->persistContext()
        );

        $created = $repository->getPayloads(StaticEntityRepository::CREATE);
        static::assertCount(1, $created);
        static::assertSame('storefront.app.SwagSeoUrlApp.blog-detail', $created[0]['routeName']);
        static::assertSame('ce_blog', $created[0]['entityName']);
    }

    public function testPersistedDoesNothingWhenNoEntitySeoUrlsAreDeclared(): void
    {
        $repository = StaticEntityRepository::of(SeoUrlTemplateCollection::class);

        $this->buildDefinition($repository)->persisted([], $this->persistContext());

        static::assertSame([], $repository->creates);
        static::assertSame([], $repository->updates);
    }

    /**
     * @param StaticEntityRepository<SeoUrlTemplateCollection> $repository
     */
    private function buildDefinition(StaticEntityRepository $repository, ?AppSeoUrlClaims $claims = null): EntitySeoUrlAppFeatureDefinition
    {
        return new EntitySeoUrlAppFeatureDefinition(
            $repository,
            $claims ?? static::createStub(AppSeoUrlClaims::class),
            $this->definitionRegistry,
            new SeoUrlGenerator(
                $this->definitionRegistry,
                static::createStub(RouterInterface::class),
                new RequestStack(),
                new Environment(new ArrayLoader()),
                new TwigVariableParserFactory(),
                new NullLogger(),
            ),
            new AclCriteriaValidator($this->definitionRegistry),
        );
    }

    /**
     * @param array<string, string> $hooksOfOtherApps hook => app name
     */
    private function claims(array $hooksOfOtherApps): AppSeoUrlClaims
    {
        $claims = static::createStub(AppSeoUrlClaims::class);
        $claims->method('hooksOfOtherApps')->willReturn($hooksOfOtherApps);

        return $claims;
    }

    /**
     * @param array<string, list<string>>|null $permissions entity name => privileges
     */
    private function persistContext(?array $permissions = null): AppPersistContext
    {
        $manifest = ManifestFixture::empty()->withName(self::APP_NAME);

        if ($permissions !== null) {
            $manifest->withPermissions($permissions);
        }

        return AppFixture::createInstallContext(AppFixture::createAppEntity(self::APP_NAME), $manifest);
    }

    private static function config(
        string $name = 'product-teaser',
        string $hook = 'teaser-page',
        string $entityName = 'product',
        string $defaultTemplate = 'teaser/{{ product.productNumber }}',
    ): AppEntitySeoUrlConfig {
        return new AppEntitySeoUrlConfig(
            name: $name,
            routeName: 'storefront.app.' . self::APP_NAME . '.' . $name,
            hook: $hook,
            entityName: $entityName,
            defaultTemplate: $defaultTemplate,
        );
    }

    private function template(string $entityName, string $template, ?string $salesChannelId = null): SeoUrlTemplateEntity
    {
        $seoUrlTemplate = new SeoUrlTemplateEntity();
        $seoUrlTemplate->setId(Uuid::randomHex());
        $seoUrlTemplate->setRouteName(self::TEASER_ROUTE);
        $seoUrlTemplate->setSalesChannelId($salesChannelId);
        $seoUrlTemplate->setEntityName($entityName);
        $seoUrlTemplate->setTemplate($template);
        $seoUrlTemplate->setIsValid(true);

        return $seoUrlTemplate;
    }
}
