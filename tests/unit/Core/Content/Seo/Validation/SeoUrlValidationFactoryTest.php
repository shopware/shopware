<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteConfig;
use Shopware\Core\Content\Seo\Validation\SeoUrlValidationFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityExists;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Validation\Constraint\RouteNotBlocked;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlValidationFactory::class)]
class SeoUrlValidationFactoryTest extends TestCase
{
    #[DataProvider('buildValidationDataProvider')]
    public function testBuildValidation(?SeoUrlRouteConfig $config, \Closure $expectsClosure): void
    {
        $factory = new SeoUrlValidationFactory();
        $context = Context::createDefaultContext();

        $definition = $factory->buildValidation($context, $config);

        static::assertSame('seo_url.create', $definition->getName());

        $expectsClosure($definition);
    }

    public static function buildValidationDataProvider(): \Generator
    {
        yield 'with route config' => [
            new SeoUrlRouteConfig(
                new CategoryDefinition(),
                'test.route',
                'test/{{ id }}'
            ),
            function (DataValidationDefinition $definition): void {
                $properties = $definition->getProperties();

                static::assertArrayHasKey('foreignKey', $properties);
                static::assertCount(2, $properties['foreignKey']);
                static::assertInstanceOf(NotBlank::class, $properties['foreignKey'][0]);
                static::assertInstanceOf(EntityExists::class, $properties['foreignKey'][1]);

                static::assertArrayHasKey('routeName', $properties);
                static::assertCount(2, $properties['routeName']);
                static::assertInstanceOf(NotBlank::class, $properties['routeName'][0]);
                static::assertInstanceOf(Type::class, $properties['routeName'][1]);

                static::assertArrayHasKey('pathInfo', $properties);
                static::assertCount(2, $properties['pathInfo']);
                static::assertInstanceOf(NotBlank::class, $properties['pathInfo'][0]);
                static::assertInstanceOf(Type::class, $properties['pathInfo'][1]);

                static::assertArrayHasKey('seoPathInfo', $properties);
                static::assertCount(3, $properties['seoPathInfo']);
                static::assertInstanceOf(NotBlank::class, $properties['seoPathInfo'][0]);
                static::assertInstanceOf(Type::class, $properties['seoPathInfo'][1]);
                static::assertInstanceOf(RouteNotBlocked::class, $properties['seoPathInfo'][2]);

                static::assertArrayHasKey('salesChannelId', $properties);
                static::assertCount(2, $properties['salesChannelId']);
                static::assertInstanceOf(NotBlank::class, $properties['salesChannelId'][0]);
                static::assertInstanceOf(EntityExists::class, $properties['salesChannelId'][1]);
            },
        ];

        yield 'without route config' => [
            null,
            function (DataValidationDefinition $definition): void {
                $properties = $definition->getProperties();

                static::assertArrayHasKey('foreignKey', $properties);
                static::assertCount(1, $properties['foreignKey']);
                static::assertInstanceOf(NotBlank::class, $properties['foreignKey'][0]);

                static::assertArrayHasKey('routeName', $properties);
                static::assertCount(2, $properties['routeName']);
                static::assertInstanceOf(NotBlank::class, $properties['routeName'][0]);
                static::assertInstanceOf(Type::class, $properties['routeName'][1]);

                static::assertArrayHasKey('pathInfo', $properties);
                static::assertCount(2, $properties['pathInfo']);
                static::assertInstanceOf(NotBlank::class, $properties['pathInfo'][0]);
                static::assertInstanceOf(Type::class, $properties['pathInfo'][1]);

                static::assertArrayHasKey('seoPathInfo', $properties);
                static::assertCount(3, $properties['seoPathInfo']);
                static::assertInstanceOf(NotBlank::class, $properties['seoPathInfo'][0]);
                static::assertInstanceOf(Type::class, $properties['seoPathInfo'][1]);
                static::assertInstanceOf(RouteNotBlocked::class, $properties['seoPathInfo'][2]);

                static::assertArrayHasKey('salesChannelId', $properties);
                static::assertCount(2, $properties['salesChannelId']);
                static::assertInstanceOf(NotBlank::class, $properties['salesChannelId'][0]);
                static::assertInstanceOf(EntityExists::class, $properties['salesChannelId'][1]);
            },
        ];
    }
}
