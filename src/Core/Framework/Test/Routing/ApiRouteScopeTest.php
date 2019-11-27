<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Routing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

class ApiRouteScopeTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function provideAllowedData()
    {
        return [
            [new Context\AdminApiSource(null, null), true],
            [new Context\AdminApiSource(null, null), false],
            [new Context\SystemSource(), false],
        ];
    }

    public function provideForbiddenData()
    {
        return [
            [new Context\SalesChannelApiSource(Uuid::randomHex()), true],
            [new Context\SystemSource(), true],
        ];
    }

    /**
     * @dataProvider provideAllowedData
     */
    public function testAllowedCombinations(Context\ContextSource $source, bool $authRequired): void
    {
        $scope = $this->getContainer()->get(ApiRouteScope::class);

        $request = Request::create('/api/foo');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, Context::createDefaultContext($source));
        $request->attributes->set('auth_required', $authRequired);

        static::assertTrue($scope->isAllowedPath($request->getPathInfo()));
        static::assertTrue($scope->isAllowed($request));
    }

    /**
     * @dataProvider provideForbiddenData
     */
    public function testForbiddenCombinations(Context\ContextSource $source, bool $authRequired): void
    {
        $scope = $this->getContainer()->get(ApiRouteScope::class);

        $request = Request::create('/api/foo');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, Context::createDefaultContext($source));
        $request->attributes->set('auth_required', $authRequired);

        static::assertFalse($scope->isAllowed($request));
    }
}
