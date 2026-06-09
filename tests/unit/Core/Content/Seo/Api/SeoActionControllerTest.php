<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\Api\SeoActionController;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoActionController::class)]
class SeoActionControllerTest extends TestCase
{
    #[TestDox('Route $routeName is guarded by the seo_url_template:update ACL privilege')]
    #[DataProvider('aclProtectedRouteProvider')]
    public function testRouteRequiresSeoUrlTemplateUpdatePrivilege(string $routeName): void
    {
        $routes = (new AttributeRouteControllerLoader())->load(SeoActionController::class);

        $route = $routes->get($routeName);

        static::assertNotNull(
            $route,
            \sprintf('Route "%s" is not defined on %s', $routeName, SeoActionController::class)
        );
        static::assertSame(['seo_url_template:update'], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'validate' => ['api.seo-url-template.validate'];
        yield 'preview' => ['api.seo-url-template.preview'];
    }
}
