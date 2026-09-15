<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Aggregate\AppSeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteCollection;
use Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteDefinition;
use Shopware\Core\Framework\App\Aggregate\AppSeoUrlRoute\AppSeoUrlRouteEntity;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppSeoUrlRouteDefinition::class)]
class AppSeoUrlRouteDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame('app_seo_url_route', $definition->getEntityName());
        static::assertSame(AppSeoUrlRouteEntity::class, $definition->getEntityClass());
        static::assertSame(AppSeoUrlRouteCollection::class, $definition->getCollectionClass());
        static::assertSame('6.7.15.0', $definition->since());
    }

    public function testFieldsAreDefined(): void
    {
        $fields = $this->createDefinition()->getFields();

        foreach (['id', 'name', 'routeName', 'hook', 'entityName', 'defaultTemplate', 'paths', 'label', 'appId', 'app'] as $field) {
            static::assertNotNull($fields->get($field), \sprintf('Field "%s" is not defined', $field));
        }
    }

    public function testBuildRouteName(): void
    {
        static::assertSame(
            'storefront.app.SwagBlog.blog-detail',
            AppSeoUrlRouteDefinition::buildRouteName('SwagBlog', 'blog-detail')
        );
    }

    private function createDefinition(): AppSeoUrlRouteDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [AppSeoUrlRouteDefinition::class, AppDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(AppSeoUrlRouteDefinition::ENTITY_NAME);
        static::assertInstanceOf(AppSeoUrlRouteDefinition::class, $definition);

        return $definition;
    }
}
