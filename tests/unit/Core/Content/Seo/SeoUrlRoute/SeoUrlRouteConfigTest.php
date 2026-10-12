<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\Exception\SeoUrlRouteConfigException;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlRouteConfig::class)]
class SeoUrlRouteConfigTest extends TestCase
{
    public function testConfig(): void
    {
        $entityDefinition = static::createStub(EntityDefinition::class);
        $config = new SeoUrlRouteConfig(
            $entityDefinition,
            'foo_bar',
            '{{ foo.bar }}',
            false,
            'fooId'
        );

        static::assertSame($entityDefinition, $config->getDefinition());
        static::assertSame('foo_bar', $config->getRouteName());
        static::assertSame('{{ foo.bar }}', $config->getTemplate());
        static::assertFalse($config->getSkipInvalid());
        static::assertSame(
            ['fooId' => 'foo-value'],
            $config->getPrimaryKeyParameter('foo-value')
        );
    }

    public function testTargetRouteNameFallsBackToRouteName(): void
    {
        $config = new SeoUrlRouteConfig(
            static::createStub(EntityDefinition::class),
            'foo_bar',
            '{{ foo.bar }}'
        );

        static::assertSame('foo_bar', $config->getTargetRouteName());
    }

    public function testTargetRouteNameIsSeparateFromTheRegistryRouteName(): void
    {
        $config = new SeoUrlRouteConfig(
            definition: static::createStub(EntityDefinition::class),
            routeName: 'storefront.app.MyApp.blog-detail',
            template: '{{ ceBlog.translated.title }}',
            primaryKeyParameterKey: 'id',
            targetRouteName: 'frontend.script_endpoint',
        );

        static::assertSame('storefront.app.MyApp.blog-detail', $config->getRouteName());
        static::assertSame('frontend.script_endpoint', $config->getTargetRouteName());
    }

    public function testPrimaryKeyParameterIsMergedIntoTheStaticRouteParameters(): void
    {
        $config = new SeoUrlRouteConfig(
            definition: static::createStub(EntityDefinition::class),
            routeName: 'storefront.app.MyApp.blog-detail',
            template: '{{ ceBlog.translated.title }}',
            primaryKeyParameterKey: 'id',
            targetRouteName: 'frontend.script_endpoint',
            routeParameters: ['hook' => 'blog-detail'],
        );

        static::assertSame(
            ['hook' => 'blog-detail', 'id' => 'foo-value'],
            $config->getPrimaryKeyParameter('foo-value')
        );
    }

    public function testPrimaryKeyParameterOverridesASameNamedRouteParameter(): void
    {
        $config = new SeoUrlRouteConfig(
            definition: static::createStub(EntityDefinition::class),
            routeName: 'storefront.app.MyApp.blog-detail',
            template: '{{ ceBlog.translated.title }}',
            primaryKeyParameterKey: 'id',
            routeParameters: ['id' => 'from-route-parameters'],
        );

        static::assertSame(['id' => 'foo-value'], $config->getPrimaryKeyParameter('foo-value'));
    }

    public function testGetPrimaryKeyParameterThrowsWhenNoKeyConfigured(): void
    {
        $defintion = static::createStub(EntityDefinition::class);
        $defintion->method('getEntityName')->willReturn('foo_bar');

        $config = new SeoUrlRouteConfig(
            $defintion,
            'foo_bar',
            '{{ foo.bar }}'
        );

        $this->expectExceptionObject(SeoUrlRouteConfigException::routeConfigMissingParameterKeyForPrimaryKey('foo_bar'));

        $config->getPrimaryKeyParameter('foo-value');
    }
}
