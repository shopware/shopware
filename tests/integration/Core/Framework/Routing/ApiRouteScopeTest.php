<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class ApiRouteScopeTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @return list<array{ContextSource, bool}>
     */
    public static function provideAllowedData(): array
    {
        return [
            [new AdminApiSource(null, null), true],
            [new AdminApiSource(null, null), false],
            [new SystemSource(), false],
        ];
    }

    /**
     * @return list<array{ContextSource, bool}>
     */
    public static function provideForbiddenData(): array
    {
        return [
            [new SalesChannelApiSource(Uuid::randomHex()), true],
            [new SystemSource(), true],
        ];
    }

    #[DataProvider('provideAllowedData')]
    public function testAllowedCombinations(ContextSource $source, bool $authRequired): void
    {
        $scope = $this->getContainer()->get(ApiRouteScope::class);

        $request = Request::create('/api/foo');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, Context::createDefaultContext($source));
        $request->attributes->set('auth_required', $authRequired);

        static::assertTrue($scope->isAllowedPath($request->getPathInfo()));
        static::assertTrue($scope->isAllowed($request));
    }

    #[DataProvider('provideForbiddenData')]
    public function testForbiddenCombinations(ContextSource $source, bool $authRequired): void
    {
        $scope = $this->getContainer()->get(ApiRouteScope::class);

        $request = Request::create('/api/foo');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, Context::createDefaultContext($source));
        $request->attributes->set('auth_required', $authRequired);

        static::assertFalse($scope->isAllowed($request));
    }
}
