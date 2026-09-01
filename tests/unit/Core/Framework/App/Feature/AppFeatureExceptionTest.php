<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Feature;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Feature\AppFeatureException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppFeatureException::class)]
class AppFeatureExceptionTest extends TestCase
{
    public function testUnknownFeature(): void
    {
        $e = AppFeatureException::unknownFeature('App\NotRegisteredConfig');

        static::assertSame(AppFeatureException::APP_FEATURE_UNKNOWN_FEATURE, $e->getErrorCode());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame('No feature definition is registered for "App\NotRegisteredConfig"', $e->getMessage());
    }

    public function testNotDeclared(): void
    {
        $e = AppFeatureException::notDeclared('0189aaaabbbbcccc0000000000000001', 'mcp_tool', 'sync-orders');

        static::assertSame(AppFeatureException::APP_FEATURE_NOT_DECLARED, $e->getErrorCode());
        static::assertSame(Response::HTTP_NOT_FOUND, $e->getStatusCode());
        static::assertSame('App "0189aaaabbbbcccc0000000000000001" does not declare the "mcp_tool" feature "sync-orders"', $e->getMessage());
    }
}
