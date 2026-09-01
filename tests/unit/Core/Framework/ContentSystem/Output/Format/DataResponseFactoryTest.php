<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Output\Format;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\LayoutReference;
use Shopware\Core\Framework\ContentSystem\Output\Format\DataResponseFactory;
use Shopware\Core\Framework\ContentSystem\Output\RenderResult;
use Shopware\Core\Framework\ContentSystem\SalesChannel\ContentDataRouteResponse;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DataResponseFactory::class)]
class DataResponseFactoryTest extends TestCase
{
    #[TestDox('creates ContentDataRouteResponse carrying the render result')]
    public function testCreateResponseReturnsContentDataRouteResponse(): void
    {
        $factory = new DataResponseFactory();

        $result = new RenderResult([], LayoutReference::create('layout-1', 'Test', null), null);

        $response = $factory->createResponse($result);

        static::assertInstanceOf(ContentDataRouteResponse::class, $response);
        static::assertSame($result, $response->getRenderResult());
    }
}
