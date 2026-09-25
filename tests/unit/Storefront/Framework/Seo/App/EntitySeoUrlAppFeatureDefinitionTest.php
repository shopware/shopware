<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateCollection;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateEntity;
use Shopware\Core\Framework\App\Lifecycle\Context\AppPersistContext;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\EntitySeoUrl;
use Shopware\Core\Framework\App\Manifest\Xml\Storefront\SeoUrl;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Framework\Seo\App\AppEntitySeoUrlConfig;
use Shopware\Storefront\Framework\Seo\App\EntitySeoUrlAppFeatureDefinition;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(EntitySeoUrlAppFeatureDefinition::class)]
class EntitySeoUrlAppFeatureDefinitionTest extends TestCase
{
    private const APP_NAME = 'SwagSeoUrlApp';

    private const TEASER_ROUTE = 'storefront.app.SwagSeoUrlApp.product-teaser';

    private EntitySeoUrlAppFeatureDefinition $definition;

    protected function setUp(): void
    {
        $this->definition = new EntitySeoUrlAppFeatureDefinition(StaticEntityRepository::of(SeoUrlTemplateCollection::class));
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
        $config = $this->config();

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
        $stored = $this->config(entityName: 'category', defaultTemplate: 'teaser/{{ category.name }}');

        $payload = $this->definition->toPayload($this->config(), $stored);

        static::assertSame('product', $payload['entityName']);
        static::assertSame('teaser/{{ product.productNumber }}', $payload['defaultTemplate']);
    }

    public function testPersistedSeedsTheSalesChannelIndependentDefaultTemplate(): void
    {
        $repository = StaticEntityRepository::of(SeoUrlTemplateCollection::class, [
            static function (Criteria $criteria): SeoUrlTemplateCollection {
                static::assertEquals(
                    [new EqualsFilter('routeName', self::TEASER_ROUTE), new EqualsFilter('salesChannelId', null)],
                    $criteria->getFilters()
                );

                return new SeoUrlTemplateCollection();
            },
        ]);

        (new EntitySeoUrlAppFeatureDefinition($repository))->persisted([$this->config()], $this->persistContext());

        static::assertSame([[
            'routeName' => self::TEASER_ROUTE,
            'entityName' => 'product',
            'template' => 'teaser/{{ product.productNumber }}',
            'isValid' => true,
            'isHeadless' => false,
        ]], $repository->getPayloads(StaticEntityRepository::CREATE));
        static::assertSame([], $repository->updates);
    }

    public function testPersistedKeepsAnExistingTemplateForTheSameEntity(): void
    {
        $repository = new StaticEntityRepository([
            new SeoUrlTemplateCollection([$this->template(entityName: 'product', template: 'my-teaser/{{ product.translated.name }}')]),
        ]);

        (new EntitySeoUrlAppFeatureDefinition($repository))->persisted([$this->config()], $this->persistContext());

        static::assertSame([], $repository->creates);
        static::assertSame([], $repository->updates);
    }

    public function testPersistedResetsTheTemplateWhenTheSeoUrlIsBoundToAnotherEntity(): void
    {
        $existing = $this->template(entityName: 'category', template: 'teaser/{{ category.translated.name }}');
        $repository = new StaticEntityRepository([new SeoUrlTemplateCollection([$existing])]);

        (new EntitySeoUrlAppFeatureDefinition($repository))->persisted([$this->config()], $this->persistContext());

        static::assertSame([], $repository->creates);
        static::assertSame([[
            'id' => $existing->getId(),
            'entityName' => 'product',
            'template' => 'teaser/{{ product.productNumber }}',
        ]], $repository->getPayloads(StaticEntityRepository::UPDATE));
    }

    public function testPersistedSeedsTheMissingTemplateOfEveryDeclaredEntitySeoUrl(): void
    {
        $repository = new StaticEntityRepository([
            new SeoUrlTemplateCollection([$this->template(entityName: 'product', template: 'teaser/{{ product.productNumber }}')]),
            new SeoUrlTemplateCollection(),
        ]);

        (new EntitySeoUrlAppFeatureDefinition($repository))->persisted(
            [
                $this->config(),
                $this->config(name: 'blog-detail', entityName: 'ce_blog', defaultTemplate: 'blog/{{ ceBlog.translated.title }}'),
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

        (new EntitySeoUrlAppFeatureDefinition($repository))->persisted([], $this->persistContext());

        static::assertSame([], $repository->creates);
        static::assertSame([], $repository->updates);
    }

    private function persistContext(): AppPersistContext
    {
        return AppFixture::createInstallContext(
            AppFixture::createAppEntity(self::APP_NAME),
            ManifestFixture::empty()->withName(self::APP_NAME),
        );
    }

    private function config(
        string $name = 'product-teaser',
        string $entityName = 'product',
        string $defaultTemplate = 'teaser/{{ product.productNumber }}',
    ): AppEntitySeoUrlConfig {
        return new AppEntitySeoUrlConfig(
            name: $name,
            routeName: 'storefront.app.' . self::APP_NAME . '.' . $name,
            hook: 'teaser-page',
            entityName: $entityName,
            defaultTemplate: $defaultTemplate,
        );
    }

    private function template(string $entityName, string $template): SeoUrlTemplateEntity
    {
        $seoUrlTemplate = new SeoUrlTemplateEntity();
        $seoUrlTemplate->setId(Uuid::randomHex());
        $seoUrlTemplate->setRouteName(self::TEASER_ROUTE);
        $seoUrlTemplate->setEntityName($entityName);
        $seoUrlTemplate->setTemplate($template);
        $seoUrlTemplate->setIsValid(true);

        return $seoUrlTemplate;
    }
}
