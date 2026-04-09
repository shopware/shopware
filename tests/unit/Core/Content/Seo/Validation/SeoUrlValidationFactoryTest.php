<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
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
    public function testConstraintsWithRouteConfig(): void
    {
        $factory = new SeoUrlValidationFactory();
        $context = Context::createDefaultContext();

        $config = new SeoUrlRouteConfig(
            new CategoryDefinition(),
            'test.route',
            'test/{{ id }}'
        );

        $definition = $factory->buildValidation($context, $config);

        static::assertSame('seo_url.create', $definition->getName());

        $foreignKeyConstraints = $definition->getProperty('foreignKey');

        static::assertCount(2, $foreignKeyConstraints);
        static::assertInstanceOf(NotBlank::class, $foreignKeyConstraints[0]);
        static::assertInstanceOf(EntityExists::class, $foreignKeyConstraints[1]);

        $this->assertCommonConstraintsExist($definition);
    }

    public function testConstraintsWithoutRouteConfig(): void
    {
        $factory = new SeoUrlValidationFactory();
        $context = Context::createDefaultContext();

        $definition = $factory->buildValidation($context, null);
        static::assertSame('seo_url.create', $definition->getName());

        $foreignKeyConstraints = $definition->getProperty('foreignKey');

        static::assertCount(1, $foreignKeyConstraints);
        static::assertInstanceOf(NotBlank::class, $foreignKeyConstraints[0]);

        $this->assertCommonConstraintsExist($definition);
    }

    private function assertCommonConstraintsExist(DataValidationDefinition $definition): void
    {
        $properties = $definition->getProperties();

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
    }
}
