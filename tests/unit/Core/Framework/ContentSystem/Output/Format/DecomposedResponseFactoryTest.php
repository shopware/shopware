<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Format\DecomposedResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentDecomposedRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DecomposedResponseFactory::class)]
class DecomposedResponseFactoryTest extends TestCase
{
    #[TestDox('creates ContentDecomposedRouteResponse carrying the render result')]
    public function testCreateResponseReturnsContentDecomposedRouteResponse(): void
    {
        $factory = new DecomposedResponseFactory();

        $result = new RenderResult([], LayoutReference::create('layout-1', 'Test', null), null);

        $response = $factory->createResponse($result);

        static::assertInstanceOf(ContentDecomposedRouteResponse::class, $response);
        static::assertSame($result, $response->getRenderResult());
    }
}
