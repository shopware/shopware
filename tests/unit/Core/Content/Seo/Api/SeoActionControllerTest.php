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

    /**
     * @param list<string> $expectedPrivileges
     */
    #[DataProvider('aclProtectedSeoUrlRouteProvider')]
    public function testRouteRequiresSeoUrlPrivilege(string $routeName, array $expectedPrivileges): void
    {
        $route = (new AttributeRouteControllerLoader())->load(SeoActionController::class)->get($routeName);

        static::assertNotNull(
            $route,
            \sprintf('Route "%s" is not defined on %s', $routeName, SeoActionController::class)
        );
        static::assertSame($expectedPrivileges, $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    /**
     * @return \Generator<string, array{0: string, 1: list<string>}>
     */
    public static function aclProtectedSeoUrlRouteProvider(): \Generator
    {
        yield 'updating a canonical url requires seo url write access' => ['api.seo-url.canonical', ['seo_url:update']];
        yield 'creating custom urls requires seo url create access' => ['api.seo-url.create', ['seo_url:create']];
        yield 'reading the template context requires template read access' => ['api.seo-url-template.context', ['seo_url_template:read']];
        yield 'reading the default template requires template read access' => ['api.seo-url-template.default', ['seo_url_template:read']];
    }
}
